<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClubOfficerTerm;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportLegacyOfficerCandidates extends Command
{
    protected $signature = 'officers:report-legacy-candidates';

    protected $description = 'Report legacy officer candidates without creating terms or granting authority';

    private const BASELINE = '6e3ad4fe0bdcc1922de413c94ae7d0a2a14af72b';

    public function handle(): int
    {
        $snapshot = Carbon::now('UTC')->toISOString();
        $clubs = DB::table('clubs')->useWritePdo()
            ->leftJoin('club_memberships as memberships', 'memberships.club_id', '=', 'clubs.id')
            ->leftJoin('roles', 'roles.id', '=', 'memberships.role_id')
            ->select('clubs.id as club_id', 'clubs.is_active', 'memberships.id as membership_id',
                'memberships.status as membership_status', 'roles.slug as role')
            ->orderBy('clubs.id')->orderBy('memberships.id')->get()->groupBy('club_id');

        $reports = [];
        foreach ($clubs as $clubId => $memberships) {
            $active = (bool) $memberships->first()->is_active;
            $offices = [];
            foreach (ClubOfficerTerm::LEGACY_ROLES as $office => $role) {
                $candidates = [];
                $excluded = [];
                foreach ($memberships->where('role', $role) as $membership) {
                    $row = [
                        'membership_id' => (int) $membership->membership_id,
                        'role' => $membership->role,
                        'status' => $membership->membership_status,
                    ];
                    if (! $active || $membership->membership_status !== 'active') {
                        $excluded[] = $row + ['reason' => $active ? 'inactive_membership' : 'inactive_club'];
                    } else {
                        $candidates[] = $row;
                    }
                }
                $classification = match (count($candidates)) {
                    0 => 'vacant',
                    1 => 'unique',
                    default => 'ambiguous',
                };
                $offices[$office] = [
                    'classification' => $classification,
                    'reason' => match ($classification) {
                        'vacant' => $active ? 'no_eligible_legacy_membership' : 'inactive_club',
                        'ambiguous' => 'multiple_eligible_legacy_memberships',
                        default => null,
                    },
                    'candidates' => $candidates,
                    'excluded' => $excluded,
                ];
            }
            $reports[] = [
                'club_id' => (int) $clubId,
                'active' => $active,
                'offices' => $offices,
                'unclassified' => $memberships->whereNotNull('membership_id')->whereNull('role')
                    ->map(fn ($row) => [
                        'membership_id' => (int) $row->membership_id,
                        'role' => null,
                        'status' => $row->membership_status,
                        'reason' => 'missing_role',
                    ])->values()->all(),
            ];
        }

        $this->line(json_encode([
            'baseline_sha' => self::BASELINE,
            'snapshot_at' => $snapshot,
            'clubs' => $reports,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
