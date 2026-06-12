<?php

namespace App\Services\Members;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MemberDataImportCommitService
{
    private const FIELD_ALIASES = [
        'member_code' => ['member_code', 'member code', 'member id', 'member no', 'member number', 'code'],
        'full_name' => ['full_name', 'full name', 'member name', 'name'],
        'phone' => ['phone', 'mobile', 'mobile number', 'phone number', 'contact number'],
        'email' => ['email', 'email address', 'mail'],
        'status' => ['status', 'member status'],
        'joined_at' => ['joined_at', 'joined at', 'joined date', 'joining date', 'date joined'],
        'blood_group' => ['blood_group', 'blood group'],
        'blood_donor_consent' => ['blood_donor_consent', 'blood donor consent', 'donor consent'],
    ];

    /**
     * Commit manual/paste JSON rows.
     * Creates only eligible new member records. Does not update or delete.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function commit(int $clubId, array $rows): array
    {
        $existingMembers = $this->existingMemberLookup($clubId);
        $batchLookup = $this->batchDuplicateLookup($rows);

        $createdCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $commitRows = DB::transaction(function () use ($clubId, $rows, &$existingMembers, $batchLookup, &$createdCount, &$skippedCount, &$failedCount) {
            $processed = [];

            foreach (array_values($rows) as $index => $row) {
                $rowNumber = $index + 1;
                $normalized = $this->normalizeRow($row);
                $validationErrors = $this->validateRow($normalized);
                $batchDuplicates = $this->batchDuplicatesForRow($rowNumber, $normalized, $batchLookup);
                $existingMatches = $this->existingMatchesForRow($normalized, $existingMembers);

                foreach ($batchDuplicates as $duplicate) {
                    $validationErrors[] = 'Duplicate '.$duplicate['field'].' inside import batch.';
                }

                $valid = $validationErrors === [];

                if (! $valid) {
                    $failedCount++;
                    $processed[] = [
                        'row_number' => $rowNumber,
                        'status' => 'failed',
                        'member_id' => null,
                        'errors' => $validationErrors,
                        'warnings' => [],
                        'normalized' => $normalized,
                    ];
                } elseif ($existingMatches !== []) {
                    $skippedCount++;
                    $warnings = [];
                    foreach ($existingMatches as $match) {
                        $warnings[] = "Existing member match found: {$match['field']} matches member ID {$match['member_id']}.";
                    }

                    $processed[] = [
                        'row_number' => $rowNumber,
                        'status' => 'skipped',
                        'member_id' => null,
                        'errors' => [],
                        'warnings' => $warnings,
                        'normalized' => $normalized,
                    ];
                } else {
                    // Create member under transaction
                    $member = Member::create([
                        'club_id' => $clubId,
                        'member_code' => $normalized['member_code'],
                        'full_name' => $normalized['full_name'],
                        'phone' => $normalized['phone'],
                        'email' => $normalized['email'],
                        'status' => $normalized['status'] ?? 'active',
                        'joined_at' => $normalized['joined_at'],
                        'blood_group' => $normalized['blood_group'],
                        'blood_donor_consent' => $normalized['blood_donor_consent'] ?? false,
                    ]);

                    $createdCount++;

                    // Dynamically update existing members lookup to prevent subsequent duplicates within the loop
                    foreach (['member_code', 'phone', 'email'] as $field) {
                        $key = $this->matchKey($field, $member->{$field});
                        if ($key !== null) {
                            $existingMembers[$field][$key][] = [
                                'member_id' => $member->id,
                                'member_code' => $member->member_code,
                                'member_name' => $member->full_name,
                                'field' => $field,
                            ];
                        }
                    }

                    $processed[] = [
                        'row_number' => $rowNumber,
                        'status' => 'created',
                        'member_id' => $member->id,
                        'errors' => [],
                        'warnings' => [],
                        'normalized' => $normalized,
                    ];
                }
            }

            return $processed;
        });

        return [
            'dry_run' => false,
            'committed' => true,
            'summary' => [
                'total_rows' => count($rows),
                'created_rows' => $createdCount,
                'skipped_rows' => $skippedCount,
                'failed_rows' => $failedCount,
            ],
            'rows' => $commitRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [
            'member_code' => null,
            'full_name' => null,
            'phone' => null,
            'email' => null,
            'status' => null,
            'joined_at' => null,
            'blood_group' => null,
            'blood_donor_consent' => null,
        ];

        foreach ($row as $heading => $value) {
            $field = $this->fieldForHeading((string) $heading);

            if (! $field) {
                continue;
            }

            $normalized[$field] = $this->normalizeValue($field, $value);
        }

        return $normalized;
    }

    private function fieldForHeading(string $heading): ?string
    {
        $normalizedHeading = $this->normalizeHeading($heading);

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedHeading === $this->normalizeHeading($alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalizeHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));
        $heading = preg_replace('/[^a-z0-9]+/', ' ', $heading) ?? $heading;

        return trim(preg_replace('/\s+/', ' ', $heading) ?? $heading);
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_bool($value) ? ($value ? 'yes' : 'no') : trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return match ($field) {
            'member_code' => strtoupper($stringValue),
            'full_name' => preg_replace('/\s+/', ' ', $stringValue),
            'phone' => $this->normalizePhone($stringValue),
            'email' => strtolower($stringValue),
            'status' => strtolower($stringValue),
            'joined_at' => $this->normalizeDate($stringValue),
            'blood_group' => strtoupper(str_replace(' ', '', $stringValue)),
            'blood_donor_consent' => $this->normalizeBoolean($stringValue),
            default => $stringValue,
        };
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? $value;
    }

    private function normalizeDate(string $value): string
    {
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeBoolean(string $value): bool|string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'yes', 'y', 'true', 'donor', 'consented' => true,
            '0', 'no', 'n', 'false', 'not donor', 'not consented' => false,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<int, string>
     */
    private function validateRow(array $normalized): array
    {
        $validator = Validator::make($normalized, [
            'member_code' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'joined_at' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'blood_donor_consent' => ['nullable', 'boolean'],
        ]);

        return $validator->errors()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, array<int, int>>>
     */
    private function batchDuplicateLookup(array $rows): array
    {
        $lookup = [
            'member_code' => [],
            'phone' => [],
            'email' => [],
        ];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = $index + 1;
            $normalized = $this->normalizeRow($row);

            foreach (array_keys($lookup) as $field) {
                $key = $this->matchKey($field, $normalized[$field] ?? null);

                if ($key === null) {
                    continue;
                }

                $lookup[$field][$key][] = $rowNumber;
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, array<string, array<int, int>>>  $batchLookup
     * @return array<int, array<string, mixed>>
     */
    private function batchDuplicatesForRow(int $rowNumber, array $normalized, array $batchLookup): array
    {
        $duplicates = [];

        foreach (array_keys($batchLookup) as $field) {
            $key = $this->matchKey($field, $normalized[$field] ?? null);

            if ($key === null) {
                continue;
            }

            $matchedRows = array_values(array_filter(
                $batchLookup[$field][$key] ?? [],
                fn (int $matchedRow): bool => $matchedRow !== $rowNumber
            ));

            if ($matchedRows === []) {
                continue;
            }

            $duplicates[] = [
                'field' => $field,
                'matched_rows' => $matchedRows,
            ];
        }

        return $duplicates;
    }

    /**
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    private function existingMemberLookup(int $clubId): array
    {
        $lookup = [
            'member_code' => [],
            'phone' => [],
            'email' => [],
        ];

        Member::query()
            ->where('club_id', $clubId)
            ->get(['id', 'member_code', 'full_name', 'phone', 'email'])
            ->each(function (Member $member) use (&$lookup): void {
                foreach (array_keys($lookup) as $field) {
                    $key = $this->matchKey($field, $member->{$field});

                    if ($key === null) {
                        continue;
                    }

                    $lookup[$field][$key][] = [
                        'member_id' => $member->id,
                        'member_code' => $member->member_code,
                        'member_name' => $member->full_name,
                        'field' => $field,
                    ];
                }
            });

        return $lookup;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $existingMembers
     * @return array<int, array<string, mixed>>
     */
    private function existingMatchesForRow(array $normalized, array $existingMembers): array
    {
        $matches = [];
        $seen = [];

        foreach (array_keys($existingMembers) as $field) {
            $key = $this->matchKey($field, $normalized[$field] ?? null);

            if ($key === null) {
                continue;
            }

            foreach ($existingMembers[$field][$key] ?? [] as $match) {
                $seenKey = $match['member_id'].'-'.$match['field'];

                if (isset($seen[$seenKey])) {
                    continue;
                }

                $seen[$seenKey] = true;
                $matches[] = $match;
            }
        }

        return $matches;
    }

    private function matchKey(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return match ($field) {
            'member_code' => strtoupper($stringValue),
            'phone' => $this->normalizePhone($stringValue),
            'email' => strtolower($stringValue),
            default => $stringValue,
        };
    }
}
