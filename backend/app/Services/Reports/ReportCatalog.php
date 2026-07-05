<?php

namespace App\Services\Reports;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportCatalog
{
    public const PERMISSION_VIEW = 'reports.view';
    public const PERMISSION_FINANCE = 'finance.club.view';

    private const DEFINITIONS = [
        'membership-summary' => [
            'title' => 'Membership Summary',
            'description' => 'Aggregate membership status counts.',
            'allowed_filters' => ['status'],
            'aggregate_only' => true,
            'sensitivity' => 'internal',
        ],
        'events-register' => [
            'title' => 'Events & Meetings Register',
            'description' => 'Verified club-scoped Events and Meetings records.',
            'allowed_filters' => ['date_from','date_to','status','type','scope','q','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'internal',
        ],
        'projects-register' => [
            'title' => 'Projects & Activities Register',
            'description' => 'Verified club-scoped Projects and Activities records.',
            'allowed_filters' => ['date_from','date_to','status','record_type','service_category','q','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'internal',
        ],
        'reminders-register' => [
            'title' => 'Reminders Register',
            'description' => 'Verified club-scoped reminder records.',
            'allowed_filters' => ['date_from','date_to','status','priority','is_all_day','q','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'internal',
        ],
        'collections-summary' => [
            'title' => 'Collections Summary',
            'description' => 'Assessed, actively collected, outstanding, and voided collection totals.',
            'allowed_filters' => ['date_from','date_to','category'],
            'aggregate_only' => true,
            'sensitivity' => 'financial',
            'financial' => true,
        ],
        'member-outstanding-balances' => [
            'title' => 'Member Outstanding Balances',
            'description' => 'Member-level assessed, paid, and outstanding collection balances.',
            'allowed_filters' => ['date_from','date_to','category','member_search','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'financial',
            'financial' => true,
        ],
        'charge-collection-status' => [
            'title' => 'Charge Collection Status',
            'description' => 'Charge-level assessment and active collection status.',
            'allowed_filters' => ['date_from','date_to','category','charge_status','charge_search','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'financial',
            'financial' => true,
        ],
        'payment-register' => [
            'title' => 'Payment Register',
            'description' => 'Recorded and voided member payments with stable receipt references.',
            'allowed_filters' => ['date_from','date_to','payment_status','payment_mode','member_search','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'financial',
            'financial' => true,
        ],
        'receipt-register' => [
            'title' => 'Receipt Register',
            'description' => 'Issued and voided receipts linked to their original payments.',
            'allowed_filters' => ['date_from','date_to','payment_status','member_search','page','per_page'],
            'aggregate_only' => false,
            'sensitivity' => 'financial',
            'financial' => true,
        ],
    ];

    public function definitions(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])
            ->values()->all();
    }

    public function definitionsFor(bool $includeFinancial): array
    {
        return array_values(array_filter(
            $this->definitions(),
            fn (array $definition): bool => $includeFinancial || ! ($definition['financial'] ?? false)
        ));
    }

    public function definition(string $key): array
    {
        if (! isset(self::DEFINITIONS[$key])) throw new NotFoundHttpException('Report not found.');
        return ['key' => $key, ...self::DEFINITIONS[$key]];
    }
}
