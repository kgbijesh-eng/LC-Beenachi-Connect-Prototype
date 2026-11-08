<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Access\OrganisationTransactionContext;
use App\Services\Finance\AccountingPeriodCloseRequest;
use App\Services\Finance\ClubAccountingPeriodCloseService;
use App\Services\Finance\ClubAccountingPeriodProvisioningRequest;
use App\Services\Finance\ClubAccountingPeriodProvisioningService;
use App\Services\Finance\LedgerAccountProvisioningRequest;
use App\Services\Finance\LedgerAccountProvisioningService;

final class AccountingAdministrationFixture
{
    public static function actions(): array
    {
        return ['account', 'period', 'close', 'account-replay', 'period-replay', 'close-replay'];
    }

    public static function prepare(string $action): array
    {
        $p = (new FinancialOperationFixture)->prepare('post', 'B5');
        $p['action'] = $action;
        if (str_ends_with($action, '-replay')) {
            self::perform([...$p, 'action' => str_replace('-replay', '', $action)]);
        }

        return $p;
    }

    public static function account(array $p): LedgerAccountProvisioningRequest
    {
        return new LedgerAccountProvisioningRequest($p['club'], 'B5-CASH', 'B5 cash', 'asset', 'debit', 'INR', $p['actor'], 'custom', null, 'CASH_AND_BANK');
    }

    public static function period(array $p): ClubAccountingPeriodProvisioningRequest
    {
        return new ClubAccountingPeriodProvisioningRequest($p['club'], '2028-29', $p['actor']);
    }

    public static function close(array $p): AccountingPeriodCloseRequest
    {
        return new AccountingPeriodCloseRequest($p['club'], $p['period'], $p['actor'], '2027-28', 'B5 controlled close');
    }

    public static function perform(array $p, ?OrganisationTransactionContext $context = null): mixed
    {
        $action = str_replace('-replay', '', $p['action']);
        [$service,$request,$method] = match ($action) {
            'account' => [app(LedgerAccountProvisioningService::class), self::account($p), 'provision'],
            'period' => [app(ClubAccountingPeriodProvisioningService::class), self::period($p), 'provision'],
            'close' => [app(ClubAccountingPeriodCloseService::class), self::close($p), 'close'],
        };

        return $context ? $service->{$method.'Within'}($context, $request) : $service->$method($request);
    }

    public static function snapshot(int $club): array
    {
        return FinancialOperationFixture::snapshot($club);
    }
}
