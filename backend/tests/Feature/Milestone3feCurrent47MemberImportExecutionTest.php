<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3feCurrent47MemberImportExecutionTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3fe_current_47_member_import_execution.md';

    public function test_3fe_document_exists_and_records_execution_only_scope(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Milestone 3FE - Current 47-Member Import Execution',
            'changes local database state and records aggregate execution evidence only',
            'It does not change runtime or frontend behavior',
            'does not place private workbook data in Git',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_records_verified_3fd_base_and_source_evidence(): void
    {
        $document = $this->executionDocument();

        foreach ([
            '876e677692adb3ca702f3de559696a78ac3618eb',
            'Member Detail Information-2026-06-17-13-49-05.xlsx',
            'CAD7A2BB1EFF5F9AAF2D56A02E4D70650C72791E201585D65604329870FE3A2A',
            'Worksheet: `Member Detail Information`',
            'Valid member records: 47.',
            'Unique Lions Member IDs: 47.',
            'Duplicate source IDs: 0.',
            'Literal `52` artefact cells: 1',
            '`_x000D_` artefact cells: 21',
            'The original workbook was not modified.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_records_external_cleaned_workbook_and_backup(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'LC_Beenachi_Current_Members_2026-06-17_Cleaned.xlsx',
            'Format: XLSX.',
            'Rows: 47 data rows plus one header row.',
            'Approved import columns: 30.',
            'Fixed Current Age column present: no.',
            'Database driver: PostgreSQL.',
            'Pre-import backup created: yes.',
            'PostgreSQL custom dump',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_records_pre_import_safety_counts(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Total member-table records: 3.',
            'Target-club member records: 3.',
            'Role-test users: 4.',
            'Source member codes already in target club: 0.',
            'Source member codes in other clubs: 0.',
            'Source-code conflicts with role-test-linked members: 0.',
            'Duplicate `club_id + member_code` pairs: 0.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_preview_accounts_for_all_rows_with_zero_failures(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Total rows: 47.',
            'Valid rows: 47.',
            'New: 47.',
            'Updates: 0.',
            'Failed: 0.',
            'Duplicate source IDs: 0.',
            'Database identity conflicts: 0.',
            'Normalized approved fields represented: 30.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_import_accounts_for_all_47_rows_with_zero_failures(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Created: 47.',
            'Updated: 0.',
            'Unchanged/skipped: 0.',
            'Failed: 0.',
            'Total accounted: 47.',
            'No direct model insertion, seeder, ad-hoc SQL import, second importer, or manual database edit was used.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_post_import_and_completeness_counts_match_source(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Final total member-table records: 50.',
            'Imported source identities in target club: 47.',
            'Distinct imported source identities in target club: 47.',
            'Imported source identities in other clubs: 0.',
            'Birth date nonnull: 47.',
            'Lion Join Date nonnull: 47.',
            'Occupation nonnull: 15; null: 32.',
            'Spouse name nonnull: 4; null: 43.',
            'Wedding anniversary nonnull: 0.',
            'Personal email nonnull: 23.',
            'Primary mobile nonnull: 30.',
            'January 1 birthdates: 12.',
            'Literal `52` remaining as a confirmed profile artefact: 0.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_idempotence_preview_reports_47_unchanged(): void
    {
        $document = $this->executionDocument();
        $section = $this->section($document, '## 11. Idempotence preview', '## 12. Role-test protection');

        foreach ([
            'Total rows: 47.',
            'New: 0.',
            'Updates: 0.',
            'Unchanged: 47.',
            'Failed: 0.',
            'Duplicate source IDs: 0.',
            'Idempotence result: PASS.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $section);
        }
    }

    public function test_3fe_preserves_role_test_accounts_and_privacy_boundaries(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Role-test users present after import: 4.',
            'Successful role-test logins: 4.',
            'Imported records automatically linked to test users: 0.',
            'Imported identities present in PST list: 47.',
            'Imported profiles sampled: 5.',
            'Sensitive fields exposed to a Normal Member for another imported member: 0.',
            'Cross-club request blocked: yes.',
            'Temporary API tokens revoked: yes.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_document_contains_no_private_member_values(): void
    {
        $document = $this->executionDocument();

        $this->assertDoesNotMatchRegularExpression('/\b\d{7,}\b/', $this->withoutHashesAndPaths($document));
        $this->assertDoesNotMatchRegularExpression('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $document);
        $this->assertDoesNotMatchRegularExpression('/\b(?:\+?\d[\d\s-]{7,})\b/', $this->withoutHashesAndPaths($document));
        $this->assertStringContainsString('No private member value is reproduced in this evidence document.', $document);
    }

    public function test_source_cleaned_workbook_and_backup_are_not_tracked(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $tracked = shell_exec('git -C '.escapeshellarg($repoRoot).' ls-files') ?? '';

        foreach ([
            'Member Detail Information-2026-06-17-13-49-05.xlsx',
            'LC_Beenachi_Current_Members_2026-06-17_Cleaned.xlsx',
            '.dump',
        ] as $blocked) {
            $this->assertStringNotContainsString($blocked, $tracked);
        }
    }

    public function test_3fe_current_git_changes_are_limited_to_two_aggregate_evidence_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3fe-current-47-member-import-execution') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3fe_current_47_member_import_execution.md',
            'backend/tests/Feature/Milestone3feCurrent47MemberImportExecutionTest.php',
        ];
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(fn (string $line): string => str_replace('\\', '/', trim(preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line)))
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)));
    }

    public function test_frontend_integration_and_month_lifecycle_remain_deferred(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'No frontend integration was performed.',
            '`app/page.tsx` and all frontend files remain unchanged.',
            'Milestone 3FF: imported-member frontend integration.',
            'Milestone 3FG: fellowship month lifecycle and dynamic continuation.',
            '3FE does not begin either deferred milestone.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fe_records_explicit_successful_closure_verdict(): void
    {
        $this->assertStringContainsString(
            'CLOSED — All 47 current member records were imported through the validated preview/commit workflow with zero failed rows, preserved role-test separation, and no private member data added to Git. Ready for Milestone 3FF imported-member frontend integration.',
            $this->executionDocument()
        );
    }

    private function executionDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function section(string $document, string $start, string $end): string
    {
        $startPosition = strpos($document, $start);
        $endPosition = strpos($document, $end);
        $this->assertNotFalse($startPosition);
        $this->assertNotFalse($endPosition);

        return substr($document, $startPosition, $endPosition - $startPosition);
    }

    private function withoutHashesAndPaths(string $document): string
    {
        $document = preg_replace('/\b[A-F0-9]{64}\b/i', '', $document) ?? $document;
        $document = preg_replace('/`[^`]*`/', '', $document) ?? $document;

        return preg_replace('/C:\\\\[^\r\n]+/', '', $document) ?? $document;
    }
}
