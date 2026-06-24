<?php

namespace App\Services\Reports;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportCatalog
{
    public const PERMISSION_VIEW = 'reports.view';

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
            'allowed_filters' => ['date_from','date_to','status','type','q','page','per_page'],
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
    ];

    public function definitions(): array
    {
        return collect(self::DEFINITIONS)
            ->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])
            ->values()->all();
    }

    public function definition(string $key): array
    {
        if (! isset(self::DEFINITIONS[$key])) throw new NotFoundHttpException('Report not found.');
        return ['key' => $key, ...self::DEFINITIONS[$key]];
    }
}
