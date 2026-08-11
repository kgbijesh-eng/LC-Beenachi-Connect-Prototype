<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\User;
use App\Services\Finance\Exceptions\AccountingPeriodProvisioningConflictException;
use App\Services\Finance\Exceptions\InvalidLionsYearException;
use App\Services\Finance\Exceptions\InvalidProvisioningActorException;
use App\Services\Finance\Exceptions\InvalidProvisioningClubException;
use App\Services\Finance\Exceptions\InvalidProvisioningRequestException;
use App\Services\Finance\Exceptions\ProvisioningPersistenceConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ClubAccountingPeriodProvisioningService
{
    public function provision(ClubAccountingPeriodProvisioningRequest $request): ClubAccountingPeriod
    {
        $normalized = $this->validateAndDerive($request);

        try {
            return DB::transaction(function () use ($request, $normalized): ClubAccountingPeriod {
                $club = Club::query()->whereKey($request->clubId)->lockForUpdate()->first();
                if (! $club) {
                    throw new InvalidProvisioningClubException('The provisioning club is invalid.');
                }
                if (! User::query()->whereKey($request->createdByUserId)->exists()) {
                    throw new InvalidProvisioningActorException('The provisioning actor is invalid.');
                }

                $existing = ClubAccountingPeriod::query()
                    ->where('club_id', $club->id)
                    ->where('lions_year', $normalized['lions_year'])
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->resolveExisting($existing, $normalized);
                }

                return ClubAccountingPeriod::create([
                    'club_id' => $club->id,
                    'lions_year' => $normalized['lions_year'],
                    'start_date' => $normalized['start_date'],
                    'end_date' => $normalized['end_date'],
                    'status' => ClubAccountingPeriod::STATUS_OPEN,
                    'created_by_user_id' => $request->createdByUserId,
                ])->fresh();
            }, 3);
        } catch (QueryException $exception) {
            $existing = ClubAccountingPeriod::query()
                ->where('club_id', $request->clubId)
                ->where('lions_year', $normalized['lions_year'])
                ->first();
            if ($existing) {
                return $this->resolveExisting($existing, $normalized);
            }

            throw new ProvisioningPersistenceConflictException('The accounting period could not be provisioned.', 0, $exception);
        }
    }

    private function validateAndDerive(ClubAccountingPeriodProvisioningRequest $request): array
    {
        if ($request->clubId < 1 || $request->createdByUserId < 1) {
            throw new InvalidProvisioningRequestException('The provisioning request is invalid.');
        }
        if (! preg_match('/\A(\d{4})-(\d{2})\z/', $request->lionsYear, $matches)) {
            throw new InvalidLionsYearException('The Lions Year is invalid.');
        }

        $startYear = (int) $matches[1];
        $endSuffix = str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
        if ($startYear < 1000 || $startYear > 9998 || $matches[2] !== $endSuffix) {
            throw new InvalidLionsYearException('The Lions Year is invalid.');
        }

        return [
            'club_id' => $request->clubId,
            'lions_year' => $request->lionsYear,
            'start_date' => sprintf('%04d-07-01', $startYear),
            'end_date' => sprintf('%04d-06-30', $startYear + 1),
            'status' => ClubAccountingPeriod::STATUS_OPEN,
            'created_by_user_id' => $request->createdByUserId,
        ];
    }

    private function resolveExisting(ClubAccountingPeriod $existing, array $normalized): ClubAccountingPeriod
    {
        if ($this->semanticPeriod($existing) !== $normalized) {
            throw new AccountingPeriodProvisioningConflictException('The Lions Year conflicts with an existing accounting period.');
        }

        return $existing->fresh();
    }

    private function semanticPeriod(ClubAccountingPeriod $period): array
    {
        return [
            'club_id' => $period->club_id,
            'lions_year' => $period->lions_year,
            'start_date' => $period->start_date->format('Y-m-d'),
            'end_date' => $period->end_date->format('Y-m-d'),
            'status' => $period->status,
            'created_by_user_id' => $period->created_by_user_id,
        ];
    }
}
