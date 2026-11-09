<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\InvalidLedgerAccountCodeException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountNormalBalanceException;
use App\Services\Finance\Exceptions\InvalidLedgerAccountTypeException;
use App\Services\Finance\Exceptions\InvalidProvisioningActorException;
use App\Services\Finance\Exceptions\InvalidProvisioningCurrencyException;
use App\Services\Finance\Exceptions\InvalidProvisioningRequestException;
use App\Services\Finance\Exceptions\LedgerAccountProvisioningConflictException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class LedgerAccountProvisioningService
{
    /** @var array<string, string> */
    private const NORMAL_BALANCES = [
        LedgerAccount::TYPE_ASSET => LedgerAccount::NORMAL_DEBIT,
        LedgerAccount::TYPE_EXPENSE => LedgerAccount::NORMAL_DEBIT,
        LedgerAccount::TYPE_LIABILITY => LedgerAccount::NORMAL_CREDIT,
        LedgerAccount::TYPE_EQUITY => LedgerAccount::NORMAL_CREDIT,
        LedgerAccount::TYPE_INCOME => LedgerAccount::NORMAL_CREDIT,
    ];

    public function provision(LedgerAccountProvisioningRequest $request): LedgerAccount
    {
        $this->validateAndNormalize($request);

        return app(OrganisationTransactionGuard::class)->run($request->clubId,
            fn (OrganisationTransactionContext $context): LedgerAccount => $this->provisionWithin($context, $request), 3);
    }

    public function provisionWithin(OrganisationTransactionContext $context, LedgerAccountProvisioningRequest $request): LedgerAccount
    {
        $normalized = $this->validateAndNormalize($request);

        return app(OrganisationTransactionGuard::class)->within($context, $request->clubId,
            fn (): LedgerAccount => DB::transaction(fn (): LedgerAccount => $this->provisionBody($context, $request, $normalized), 3));
    }

    private function provisionBody(OrganisationTransactionContext $context, LedgerAccountProvisioningRequest $request, array $normalized): LedgerAccount
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

            $existing = LedgerAccount::query()
                ->where('club_id', $context->organisationId)
                ->where('code', $normalized['code'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $result = $this->resolveExisting($existing, $normalized);
            } else {
                $result = LedgerAccount::create([
                    'club_id' => $context->organisationId,
                    'code' => $normalized['code'],
                    'name' => $normalized['name'],
                    'account_type' => $normalized['account_type'],
                    'normal_balance' => $normalized['normal_balance'],
                    'currency' => LedgerAccount::CURRENCY_INR,
                    'is_active' => true,
                    'created_by_user_id' => $request->createdByUserId,
                    'origin_kind' => $normalized['origin_kind'],
                    'origin_template_code' => $normalized['origin_template_code'],
                    'statement_classification' => $normalized['statement_classification'],
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
            $existing = LedgerAccount::query()->where('club_id', $context->organisationId)
                ->where('code', $normalized['code'])->lockForUpdate()->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveExisting($existing, $normalized);
        }
        // Release/completion errors are deliberately outside the recovery catch.
        DB::statement('RELEASE SAVEPOINT '.$savepoint);

        return $result;
    }

    private function validateAndNormalize(LedgerAccountProvisioningRequest $request): array
    {
        if ($request->clubId < 1 || $request->createdByUserId < 1) {
            throw new InvalidProvisioningRequestException('The provisioning request is invalid.');
        }

        $code = strtoupper(trim($request->code));
        if (strlen($code) < 2 || strlen($code) > 40
            || ! preg_match('/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*\z/', $code)) {
            throw new InvalidLedgerAccountCodeException('The ledger account code is invalid.');
        }

        $name = trim($request->name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidProvisioningRequestException('The ledger account name is invalid.');
        }
        if (! array_key_exists($request->accountType, self::NORMAL_BALANCES)) {
            throw new InvalidLedgerAccountTypeException('The ledger account type is invalid.');
        }
        if (! in_array($request->normalBalance, [LedgerAccount::NORMAL_DEBIT, LedgerAccount::NORMAL_CREDIT], true)) {
            throw new InvalidLedgerAccountNormalBalanceException('The ledger account normal balance is invalid.');
        }
        if (self::NORMAL_BALANCES[$request->accountType] !== $request->normalBalance) {
            throw new InvalidLedgerAccountNormalBalanceException('The ledger account type and normal balance are incompatible.');
        }
        if ($request->currency !== LedgerAccount::CURRENCY_INR) {
            throw new InvalidProvisioningCurrencyException('The provisioning currency is unsupported.');
        }
        $origin = AccountOriginKind::tryFrom($request->originKind);
        $classification = StatementClassification::tryFrom($request->statementClassification);
        if (! $origin || ! $classification || ($origin === AccountOriginKind::CATALOGUE && $request->originTemplateCode === null)
            || ($origin === AccountOriginKind::CUSTOM && $request->originTemplateCode !== null)) {
            throw new InvalidProvisioningRequestException('Canonical account origin and statement classification are required.');
        }
        if ($origin === AccountOriginKind::CATALOGUE) {
            $template = collect(app(ApprovedLedgerAccountTemplateCatalog::class)->definitions())->firstWhere('templateCode', $request->originTemplateCode);
            if (! $template || $template->accountType !== $request->accountType || $template->normalBalance !== $request->normalBalance || $template->statementClassification !== $classification->value) {
                throw new InvalidProvisioningRequestException('Catalogue account metadata does not match its template.');
            }
        }

        return [
            'club_id' => $request->clubId,
            'code' => $code,
            'name' => $name,
            'account_type' => $request->accountType,
            'normal_balance' => $request->normalBalance,
            'currency' => LedgerAccount::CURRENCY_INR,
            'is_active' => true,
            'created_by_user_id' => $request->createdByUserId,
            'origin_kind' => $origin->value,
            'origin_template_code' => $request->originTemplateCode,
            'statement_classification' => $classification->value,
        ];
    }

    private function resolveExisting(LedgerAccount $existing, array $normalized): LedgerAccount
    {
        if ($this->semanticAccount($existing) !== $normalized) {
            throw new LedgerAccountProvisioningConflictException('The ledger account code conflicts with an existing account.');
        }

        return $existing->fresh();
    }

    private function semanticAccount(LedgerAccount $account): array
    {
        return [
            'club_id' => $account->club_id,
            'code' => $account->code,
            'name' => $account->name,
            'account_type' => $account->account_type,
            'normal_balance' => $account->normal_balance,
            'currency' => $account->currency,
            'is_active' => $account->is_active,
            'created_by_user_id' => $account->created_by_user_id,
            'origin_kind' => $account->origin_kind,
            'origin_template_code' => $account->origin_template_code,
            'statement_classification' => $account->statement_classification,
        ];
    }
}
