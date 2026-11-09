<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\User;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use Illuminate\Auth\Access\AuthorizationException;

class MemberDataImportCommitService
{
    public function __construct(
        private readonly MemberDataImportPreviewService $previewService
    ) {
    }

    /**
     * Revalidate and atomically commit rows reviewed through the preview contract.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function commit(int $clubId, array $rows, User $actor): array
    {
        return app(OrganisationTransactionGuard::class)->run($clubId, function ($context) use ($clubId, $rows, $actor): array {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $clubId, $actor, 'club.view');
            $role = $actor->clubMemberships()->with('role')->where('club_id', $clubId)->where('status', 'active')->first()?->role?->slug;
            if (! in_array($role, ['president-admin', 'secretary', 'treasurer'], true)) {
                throw new AuthorizationException();
            }

            return $this->commitRows($clubId, $rows);
        });
    }

    private function commitRows(int $clubId, array $rows): array
    {
        $preview = $this->previewService->preview($clubId, $rows);
        $counts = [
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ];

        $processed = [];

        foreach ($preview['rows'] as $row) {
            $normalized = $row['normalized'];
            $status = $row['intent'];
            $member = null;
            $warnings = [];

            if ($status === 'reject') {
                $counts['failed_rows']++;
                $commitStatus = 'failed';
            } elseif ($status === 'create_preview') {
                $member = Member::query()->create([
                    'club_id' => $clubId,
                    ...$this->previewService->valuesForCreate($normalized),
                ]);
                $counts['created_rows']++;
                $commitStatus = 'created';
            } elseif ($status === 'update_preview') {
                $member = $this->memberForPreviewRow($clubId, $row);

                if (! $member) {
                    $counts['failed_rows']++;
                    $commitStatus = 'failed';
                    $row['errors'][] = 'member_code: Reviewed member no longer exists in this club.';
                    $row['error_details'][] = [
                        'row_number' => $row['row_number'],
                        'member_code' => $normalized['member_code'],
                        'field' => 'member_code',
                        'reason' => 'Reviewed member no longer exists in this club.',
                    ];
                } else {
                    $changes = $this->previewService->changesForMember($member, $normalized);
                    $member->update($changes);
                    $counts['updated_rows']++;
                    $commitStatus = 'updated';
                }
            } else {
                $member = $this->memberForPreviewRow($clubId, $row);
                $counts['skipped_rows']++;
                $commitStatus = 'skipped';

                foreach ($row['duplicate_matches']['existing'] as $match) {
                    $warnings[] = "Existing member match found: {$match['field']} matches member ID {$match['member_id']}.";
                }
            }

            $processed[] = [
                'row_number' => $row['row_number'],
                'status' => $commitStatus,
                'member_id' => $member?->id,
                'changed_fields' => $commitStatus === 'updated' ? $row['changed_fields'] : [],
                'errors' => $row['errors'],
                'error_details' => $row['error_details'],
                'warnings' => $warnings,
                'normalized' => $normalized,
            ];
        }

        return [
            'dry_run' => false,
            'committed' => true,
            'summary' => [
                'total_rows' => count($rows),
                ...$counts,
            ],
            'rows' => $processed,
        ];
    }

    private function memberForPreviewRow(int $clubId, array $row): ?Member
    {
        $identityMatch = collect($row['duplicate_matches']['existing'] ?? [])
            ->first(fn (array $match): bool => $match['field'] === 'member_code');

        if (! $identityMatch) {
            return null;
        }

        return Member::query()
            ->where('club_id', $clubId)
            ->whereKey($identityMatch['member_id'])
            ->first();
    }
}
