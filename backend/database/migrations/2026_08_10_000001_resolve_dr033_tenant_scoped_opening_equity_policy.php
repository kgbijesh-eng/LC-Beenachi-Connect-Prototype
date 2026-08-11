<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceChecks(false);
        $this->replaceContentGuard(false);
    }

    public function down(): void
    {
        $this->replaceContentGuard(true);
        $this->replaceChecks(true);
    }

    private function replaceChecks(bool $legacy): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('ALTER TABLE club_accounting_cutover_packages DROP CONSTRAINT IF EXISTS cutover_pkg_dr033_chk');
            DB::unprepared('ALTER TABLE club_accounting_cutover_reviews DROP CONSTRAINT IF EXISTS cutover_review_class_chk');
            $package = $legacy
                ? "dr033_classification IS NULL OR dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"
                : "dr033_classification IS NULL OR dr033_classification ~ '^[A-Z0-9][A-Z0-9._:-]{2,39}$'";
            $review = $legacy
                ? "selected_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"
                : "selected_classification ~ '^[A-Z0-9][A-Z0-9._:-]{2,39}$'";
            DB::unprepared("ALTER TABLE club_accounting_cutover_packages ADD CONSTRAINT cutover_pkg_dr033_chk CHECK ({$package})");
            DB::unprepared("ALTER TABLE club_accounting_cutover_reviews ADD CONSTRAINT cutover_review_class_chk CHECK ({$review})");

            return;
        }

        foreach ([['club_accounting_cutover_packages', 'cutover_pkg_dr033_chk'], ['club_accounting_cutover_reviews', 'cutover_review_class_chk']] as [$table, $name]) {
            foreach (['insert', 'update'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}_{$operation}");
            }
        }
        $package = $legacy
            ? "NEW.dr033_classification IS NULL OR NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"
            : "NEW.dr033_classification IS NULL OR (length(NEW.dr033_classification) BETWEEN 3 AND 40 AND NEW.dr033_classification GLOB '[A-Z0-9]*' AND NEW.dr033_classification NOT GLOB '*[^-A-Z0-9._:]*')";
        $review = $legacy
            ? "NEW.selected_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"
            : "length(NEW.selected_classification) BETWEEN 3 AND 40 AND NEW.selected_classification GLOB '[A-Z0-9]*' AND NEW.selected_classification NOT GLOB '*[^-A-Z0-9._:]*'";
        foreach ([['club_accounting_cutover_packages', 'cutover_pkg_dr033_chk', $package], ['club_accounting_cutover_reviews', 'cutover_review_class_chk', $review]] as [$table, $name, $expression]) {
            foreach (['insert', 'update'] as $operation) {
                DB::unprepared("CREATE TRIGGER {$name}_{$operation} BEFORE ".strtoupper($operation)." ON {$table} FOR EACH ROW WHEN NOT ({$expression}) BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }
        }
    }

    private function replaceContentGuard(bool $legacy): void
    {
        $selection = $legacy
            ? "NEW.dr033_classification IN ('OPENING-BALANCE-EQUITY','ACCUMULATED-SURPLUS')"
            : 'NEW.dr033_classification IS NOT NULL';
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS cutover_package_accounting_content_guard');
            DB::unprepared("CREATE TRIGGER cutover_package_accounting_content_guard BEFORE UPDATE ON club_accounting_cutover_packages FOR EACH ROW WHEN OLD.status <> 'draft' AND (NEW.club_id IS NOT OLD.club_id OR NEW.lions_year IS NOT OLD.lions_year OR NEW.cutover_date IS NOT OLD.cutover_date OR NEW.chart_version IS NOT OLD.chart_version OR NEW.package_version IS NOT OLD.package_version OR NEW.currency IS NOT OLD.currency OR NEW.reconciliation_difference_paise IS NOT OLD.reconciliation_difference_paise OR NEW.dr033_classification IS NOT OLD.dr033_classification) AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND OLD.dr033_classification IS NULL AND {$selection}) BEGIN SELECT RAISE(ABORT, 'cutover_package_accounting_content_guard'); END");

            return;
        }

        DB::unprepared("CREATE OR REPLACE FUNCTION cutover_guard_accounting_content() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF OLD.status <> 'draft' AND (NEW.club_id, NEW.lions_year, NEW.cutover_date, NEW.chart_version, NEW.package_version, NEW.currency, NEW.reconciliation_difference_paise, NEW.dr033_classification) IS DISTINCT FROM (OLD.club_id, OLD.lions_year, OLD.cutover_date, OLD.chart_version, OLD.package_version, OLD.currency, OLD.reconciliation_difference_paise, OLD.dr033_classification) AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND OLD.dr033_classification IS NULL AND {$selection}) THEN RAISE EXCEPTION 'prepared cutover accounting content is immutable'; END IF; IF OLD.status <> 'draft' AND NEW.canonical_checksum IS DISTINCT FROM OLD.canonical_checksum AND NOT (OLD.status = 'internally_verified' AND NEW.status = 'accounting_reviewed' AND NEW.canonical_checksum IS NULL) AND NOT (OLD.status = 'accounting_reviewed' AND NEW.status = 'accounting_reviewed' AND OLD.canonical_checksum IS NULL AND NEW.canonical_checksum IS NOT NULL) THEN RAISE EXCEPTION 'cutover checksum is immutable'; END IF; RETURN NEW; END; \$\$");
    }
};
