<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3giReportsAuthorizationApiContractApprovalTest extends TestCase
{
    public function test_contract_is_fixed_private_and_non_financial(): void
    {
        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gi_reports_authorization_api_contract_approval.md');
        foreach ([
            'cff5b5e573bd6bf16b91f169d4c46a53befed3bd',
            '`membership-summary`', '`events-register`', '`projects-register`', '`reminders-register`',
            '`collections-summary`', 'Deferred and absent', '`reports.view`',
            'Normal Member has no Reports permission', 'Unknown/deferred keys return 404',
            'No POST, PATCH, or DELETE route', 'Irrelevant or unknown filters return 422',
            'Maximum date range is 366 days', 'aggregate-only',
            'Unsupported-source zeros are forbidden', 'Cross-club requests fail closed',
            'Never expose contact details', 'No report table', 'No report table',
            'Financial reporting remains deferred', '3GJ may implement', '3GK may implement',
            'GO - 3GJ may open',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }
    }
}
