<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3baDeploymentSaasStrategyReadinessReviewTest extends TestCase
{
    public function test_3ba_strategy_document_exists_and_records_status(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Milestone 3BA - Deployment and SaaS Strategy Readiness Review', $document);
        $this->assertStringContainsString('Status: STRATEGY REVIEW ONLY', $document);
        $this->assertStringContainsString('docs/tests-only', $document);
        $this->assertStringContainsString('No runtime implementation is allowed in 3BA.', $document);
    }

    public function test_3ba_is_based_on_full_audit_through_3az(): void
    {
        $document = $this->strategyDocument();
        $audit = $this->auditDocument();

        $this->assertStringContainsString('audit/full-project-documentation-and-deployment-strategy-verification-through-3az', $document);
        $this->assertStringContainsString('f6ecafb72148b030c90ab5d60ca9511cbca8b139', $document);
        $this->assertStringContainsString('Milestone 1A through 3AZ verified', $document);
        $this->assertStringContainsString('Historical milestone documents were not modified', $document);
        $this->assertStringContainsString('Deployment strategy was clarified', $document);

        $this->assertStringContainsString('full project documentation', strtolower($audit));
        $this->assertStringContainsString('3AZ', $audit);
    }

    public function test_3ba_records_later_strategy_reconciliation_without_rewriting_3az(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Milestone 3AZ correctly selected:', $document);
        $this->assertStringContainsString('Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization', $document);
        $this->assertStringContainsString('This is a later strategic reconciliation.', $document);
        $this->assertStringContainsString('It does not rewrite 3AZ.', $document);
        $this->assertStringContainsString('It does not change the historical 3AZ decision.', $document);
    }

    public function test_3ba_records_selected_deployment_model(): void
    {
        $document = $this->strategyDocument();

        $deploymentRules = [
            'One shared source codebase',
            'Separate deployed app instance per pilot club',
            'Separate database per club instance',
            'Separate environment/configuration per club instance',
            'Separate storage folder or bucket per club instance',
            'Club-specific branding and configuration',
            'No code fork per club',
        ];

        foreach ($deploymentRules as $rule) {
            $this->assertStringContainsString($rule, $document);
        }
    }

    public function test_3ba_records_pilot_clubs_and_reason_for_isolated_instances_first(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Lions Club Beenachi', $document);
        $this->assertStringContainsString('Rotary Central Bathery', $document);

        $reasons = [
            'Reduce cross-club data leakage risk',
            'Simplify early deployment',
            'Simplify backup and restore per club',
            'Simplify support and troubleshooting',
            'Allow safer pilot validation',
            'Avoid premature global SaaS overengineering',
            'Preserve a future migration path to shared SaaS',
        ];

        foreach ($reasons as $reason) {
            $this->assertStringContainsString($reason, $document);
        }
    }

    public function test_3ba_blocks_code_forks_and_hardcoded_client_logic(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('The platform must not be forked per club.', $document);

        $blockedItems = [
            'Separate Lions codebase',
            'Separate Rotary codebase',
            'Separate BNI codebase',
            'Client-specific code forks',
            'Hardcoded club-specific business logic',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }
    }

    public function test_3ba_defines_initial_deployment_unit(): void
    {
        $document = $this->strategyDocument();

        $deploymentUnitItems = [
            'Own domain or subdomain',
            'Own backend environment configuration',
            'Own frontend environment configuration where needed',
            'Own PostgreSQL database',
            'Own storage path or bucket',
            'Own seeded club record',
            'Own seeded local admin or president/admin user',
            'Own backup schedule',
            'Own support contact record',
            'Own branding configuration',
        ];

        foreach ($deploymentUnitItems as $item) {
            $this->assertStringContainsString($item, $document);
        }
    }

    public function test_3ba_tracks_future_saas_readiness_gaps(): void
    {
        $document = $this->strategyDocument();

        $gaps = [
            'Tenant hierarchy',
            'Organization type',
            'Club or chapter structure',
            'District or region support',
            'Configurable role labels',
            'Configurable permissions',
            'Financial year setting per club',
            'Timezone and locale support',
            'Multi-currency readiness',
            'Subscription and billing model',
            'Super-admin or support console',
            'Onboarding checklist',
            'Deployment checklist',
            'Data isolation policy',
            'Backup and restore policy',
            'Data export policy',
            'Privacy and data-retention policy',
            'Audit log review',
            'Migration path from isolated instances to shared SaaS',
        ];

        foreach ($gaps as $gap) {
            $this->assertStringContainsString($gap, $document);
        }
    }

    public function test_3ba_records_business_rollout_and_positioning(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Complete pilot deployment readiness', $document);
        $this->assertStringContainsString('Implement and validate the platform for Lions Club Beenachi', $document);
        $this->assertStringContainsString('Implement and validate the platform for Rotary Central Bathery', $document);
        $this->assertStringContainsString('Start wider marketing to Lions Clubs, Rotary Clubs, BNI chapters, and other membership-based clubs', $document);

        $targets = [
            'Lions Clubs',
            'Rotary Clubs',
            'BNI chapters',
            'Local associations',
            'Chambers',
            'Service clubs',
            'Membership-based non-profit clubs',
        ];

        foreach ($targets as $target) {
            $this->assertStringContainsString($target, $document);
        }
    }

    public function test_3ba_records_deferred_items_without_implementing_them(): void
    {
        $document = $this->strategyDocument();

        $deferredItems = [
            'Club finance reports',
            'Balance sheet',
            'Reminders',
            'Documents',
            'Announcements',
            'Projects',
            'Meeting notices',
            'Minutes visibility',
            'Donor consent search',
            'Fellowship sponsorship',
            'Special collections',
            'Excel import',
            'Audit log review UI',
            'Full global SaaS readiness',
        ];

        foreach ($deferredItems as $item) {
            $this->assertStringContainsString($item, $document);
        }
    }

    public function test_3ba_records_corrected_forward_sequence(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('3AZ - Club Finance Reports and Balance Sheet Scope Gate - closed', $document);
        $this->assertStringContainsString('3BA - Deployment and SaaS Strategy Readiness Review - docs/tests-only', $document);
        $this->assertStringContainsString('3BB - Club Finance Reports and Balance Sheet Read API Authorization - docs/tests-only', $document);
        $this->assertStringContainsString('3BC - Club Finance Reports and Balance Sheet Read API Narrow Implementation', $document);
        $this->assertStringContainsString('3BD - Read API Validation and Closure', $document);
        $this->assertStringContainsString('Later - Reports Frontend Scope Gate', $document);
    }

    public function test_3ba_blocks_runtime_routes_and_implementation_drift(): void
    {
        $document = $this->strategyDocument();

        $blockedItems = [
            'app/page.tsx',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend services',
            'backend models',
            'migrations',
            'seeders',
            'frontend screens',
            'report implementation',
            'balance sheet implementation',
            'exports',
            'reminders',
            'Excel import',
            'payment history',
            'payment cancellation',
            'payment reversal',
            'special collection allocation workflow',
            'fellowship payment allocation workflow',
            'receipt sharing workflow',
            'subscription implementation',
            'super-admin implementation',
            'shared SaaS tenant implementation',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }

        $routeUris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ($routeUris as $uri) {
            $this->assertStringNotContainsString('3ba', $uri);
            $this->assertStringNotContainsString('deployment', $uri);
            $this->assertStringNotContainsString('saas', $uri);
            $this->assertStringNotContainsString('subscription', $uri);
            $this->assertStringNotContainsString('super-admin', $uri);
            if (! in_array($uri, [
                'api/v1/clubs/{club}/finance/reports',
                'api/v1/clubs/{club}/finance/balance-sheet',
            ], true)) {
                $this->assertStringNotContainsString('finance/reports', $uri);
                $this->assertStringNotContainsString('balance-sheet', $uri);
            }
        }
    }

    public function test_3ba_allows_only_strategy_document_and_test(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Allowed files in 3BA:', $document);
        $this->assertStringContainsString('backend/docs/milestone_3ba_deployment_saas_strategy_readiness_review.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3baDeploymentSaasStrategyReadinessReviewTest.php', $document);
    }

    public function test_3ba_closure_decision_selects_3bb_next(): void
    {
        $document = $this->strategyDocument();

        $this->assertStringContainsString('Deployment and SaaS strategy readiness is reviewed.', $document);
        $this->assertStringContainsString('one shared codebase and separate deployed app/database/config/storage per pilot club first', $document);
        $this->assertStringContainsString('Milestone 3BB - Club Finance Reports and Balance Sheet Read API Authorization', $document);
    }

    private function strategyDocument(): string
    {
        $path = base_path('docs/milestone_3ba_deployment_saas_strategy_readiness_review.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function auditDocument(): string
    {
        $path = base_path('docs/full_project_documentation_and_deployment_strategy_verification_through_3az.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
