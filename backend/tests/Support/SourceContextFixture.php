<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ClubCharge;
use App\Models\MemberPayment;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Finance\SourceAccountingIntegrationService;

final class SourceContextFixture
{
    public static function methods(): array
    {
        return ['recordChargeCoverageDecision', 'recordPaymentCoverageDecision', 'postCharge', 'postPayment', 'validateChargeReplay', 'validatePaymentReplay', 'reverse'];
    }

    public static function prepare(string $method): array
    {
        $p = (new FinancialOperationFixture)->prepare(str_contains($method, 'Payment') ? 'collections-void' : 'collections-cancel');
        if ($method === 'reverse') {
            FinancialOperationFixture::perform($p);
        }

        return $p;
    }

    public static function invoke(string $method, array $p, OrganisationTransactionContext $context): mixed
    {
        return app(SourceAccountingIntegrationService::class)->{$method.'Within'}($context, ...self::arguments($method, $p));
    }

    public static function legacy(string $method, array $p): mixed
    {
        return app(SourceAccountingIntegrationService::class)->$method(...self::arguments($method, $p));
    }

    private static function arguments(string $method, array $p): array
    {
        $actor = User::findOrFail($p['actor']);
        if ($method === 'reverse') {
            $charge = ClubCharge::findOrFail($p['charge']);

            return [$p['club'], 'member_charge', $charge->public_id, $charge->issued_at->toDateString(), $charge->issued_at->toISOString(), '2027-08-12', $actor, 'B3 probe'];
        }
        $source = str_contains($method, 'Payment') ? MemberPayment::findOrFail($p['collection_payment']) : ClubCharge::findOrFail($p['charge']);

        return str_starts_with($method, 'validate') ? [$source] : [$source, $actor];
    }
}
