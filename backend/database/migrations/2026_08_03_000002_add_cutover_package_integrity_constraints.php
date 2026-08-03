<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHECKS = [
        ['club_accounting_cutover_packages', 'cutover_pkg_version_chk', 'NEW.package_version >= 1'],
        ['club_accounting_cutover_packages', 'cutover_pkg_lock_chk', 'NEW.lock_version >= 1'],
        ['club_accounting_cutover_packages', 'cutover_pkg_currency_chk', "NEW.currency = 'INR'"],
        ['club_accounting_cutover_packages', 'cutover_pkg_identity_chk', "NEW.cutover_date = '2027-07-01' AND NEW.lions_year = '2027-28' AND NEW.chart_version = 'CLUB-COA-2026-01'"],
        ['club_accounting_cutover_packages', 'cutover_pkg_status_chk', "NEW.status IN ('draft','prepared','internally_verified','accounting_reviewed','business_approved','dry_run_accepted','activation_authorized','posted','rejected','superseded','reversed')"],
        ['club_accounting_cutover_packages', 'cutover_pkg_dr033_chk', "NEW.dr033_classification IS NULL OR NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"],
        ['club_accounting_cutover_schedule_rows', 'cutover_row_amount_chk', 'NEW.amount_paise >= 0'],
        ['club_accounting_cutover_schedule_rows', 'cutover_row_direction_chk', "NEW.direction IN ('debit','credit')"],
        ['club_accounting_cutover_schedule_rows', 'cutover_row_type_chk', "NEW.schedule_type IN ('cash_on_hand','bank_account','member_receivables','liability','external_restriction','other_asset','general_club_fund','service_activity_fund','fellowship_member_activity_fund','equity_preview')"],
        ['club_accounting_cutover_schedule_rows', 'cutover_row_group_chk', "NEW.presentation_group IS NULL OR NEW.presentation_group IN ('GENERAL-CLUB-FUND','SERVICE-ACTIVITY-FUND','FELLOWSHIP-MEMBER-ACTIVITY-FUND')"],
        ['club_accounting_cutover_evidence_references', 'cutover_evidence_status_chk', "NEW.verification_status IN ('unverified','verified','rejected')"],
        ['club_accounting_cutover_reviews', 'cutover_review_class_chk', "NEW.selected_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"],
        ['club_accounting_cutover_reviews', 'cutover_review_status_chk', "NEW.status IN ('current','invalidated')"],
        ['club_accounting_cutover_approvals', 'cutover_approval_status_chk', "NEW.status IN ('current','invalidated')"],
    ];

    public function up(): void
    {
        foreach (self::CHECKS as [$table, $name, $expression]) {
            $this->addCheck($table, $name, $expression);
        }

        DB::statement("CREATE UNIQUE INDEX cutover_one_current_review_idx ON club_accounting_cutover_reviews (cutover_package_id) WHERE status = 'current'");
        DB::statement("CREATE UNIQUE INDEX cutover_one_current_approval_idx ON club_accounting_cutover_approvals (cutover_package_id) WHERE status = 'current'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cutover_one_current_approval_idx');
        DB::statement('DROP INDEX IF EXISTS cutover_one_current_review_idx');

        foreach (array_reverse(self::CHECKS) as [$table, $name]) {
            $this->dropCheck($table, $name);
        }
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                DB::unprepared(
                    "CREATE TRIGGER {$name}_{$suffix} BEFORE {$operation} ON {$table} ".
                    "FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END"
                );
            }

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK (".str_replace('NEW.', '', $expression).')');
    }

    private function dropCheck(string $table, string $name): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}_insert");
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}_update");

            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
    }
};
