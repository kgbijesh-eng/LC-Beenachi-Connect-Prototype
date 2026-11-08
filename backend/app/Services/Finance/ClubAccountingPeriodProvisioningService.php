<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingPeriod;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\AccountingPeriodProvisioningConflictException;
use App\Services\Finance\Exceptions\InvalidLionsYearException;
use App\Services\Finance\Exceptions\InvalidProvisioningActorException;
use App\Services\Finance\Exceptions\InvalidProvisioningRequestException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ClubAccountingPeriodProvisioningService
{
    public function provision(ClubAccountingPeriodProvisioningRequest $request): ClubAccountingPeriod
    {
        $this->validateAndDerive($request);

        return app(OrganisationTransactionGuard::class)->run($request->clubId,
            fn (OrganisationTransactionContext $context): ClubAccountingPeriod => $this->provisionWithin($context, $request), 3);
    }

    public function provisionWithin(OrganisationTransactionContext $context, ClubAccountingPeriodProvisioningRequest $request): ClubAccountingPeriod
    {
        $normalized = $this->validateAndDerive($request);

        return app(OrganisationTransactionGuard::class)->within($context, $request->clubId,
            fn (): ClubAccountingPeriod => DB::transaction(fn (): ClubAccountingPeriod => $this->provisionBody($context, $request, $normalized), 3));
    }

    private function provisionBody(OrganisationTransactionContext $context, ClubAccountingPeriodProvisioningRequest $request, array $normalized): ClubAccountingPeriod
    {
        $context->assertUsable($request->clubId);
        // This private SQL savepoint proves rollback independently of Laravel depth
        // or exception SQLSTATE. The B1 club lock predates it and remains held.
        $savepoint = 'b5_provision_'.bin2hex(random_bytes(12));
        DB::statement('SAVEPOINT '.$savepoint);
        try {
            if (! User::query()->whereKey($request->createdByUserId)->exists()) {
                throw new InvalidProvisioningActorException('The provisioning actor is invalid.');
            }

            $existing = ClubAccountingPeriod::query()
                ->where('club_id', $context->organisationId)
                ->where('lions_year', $normalized['lions_year'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $result = $this->resolveExisting($existing, $normalized);
            } else {
                $result = ClubAccountingPeriod::create([
                    'club_id' => $context->organisationId,
                    'lions_year' => $normalized['lions_year'],
                    'start_date' => $normalized['start_date'],
                    'end_date' => $normalized['end_date'],
                    'status' => ClubAccountingPeriod::STATUS_OPEN,
                    'created_by_user_id' => $request->createdByUserId,
                ])->fresh();
            }
        } catch (QueryException $exception) {
            // Preserve the framework's genuine concurrency retry path; never replay
            // instead of allowing the outer owner to retry or exhaust its budget.
            if (app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            try {
                DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
                DB::statement('RELEASE SAVEPOINT '.$savepoint);
            } catch (\Throwable) {
                // Unknown rollback/completion outcome must preserve the original failure.
                throw $exception;
            }
            $context->assertUsable($request->clubId);
            if (! User::query()->whereKey($request->createdByUserId)->exists()) {
                throw new InvalidProvisioningActorException('The provisioning actor is invalid.');
            }
            $existing = ClubAccountingPeriod::query()->where('club_id', $context->organisationId)
                ->where('lions_year', $normalized['lions_year'])->lockForUpdate()->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveExisting($existing, $normalized);
        }
        // Release/completion errors are deliberately outside the recovery catch.
        DB::statement('RELEASE SAVEPOINT '.$savepoint);

        return $result;
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
