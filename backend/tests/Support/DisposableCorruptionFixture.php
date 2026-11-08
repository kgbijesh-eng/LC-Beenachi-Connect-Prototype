<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Test-only, data-only plans. Never accepts a callback or runs a financial service. */
final class DisposableCorruptionFixture
{
    private const TRIGGERS = [
        'club_accounting_source_integrations' => 'club_accounting_source_integrations_immutable',
        'club_accounting_source_integration_events' => 'club_accounting_source_integration_events_immutable',
        'club_accounting_source_coverage_decisions' => 'club_accounting_source_coverage_decisions_immutable',
    ];

    private const COLUMNS = [
        'club_accounting_source_integrations' => ['canonical_source_key', 'integration_checksum', 'source_binding'],
        'club_accounting_source_integration_events' => ['reversal_journal_entry_id'],
        'club_accounting_source_coverage_decisions' => ['integration_required', 'decision_reason'],
        'payment_allocations' => ['member_charge_id'],
        'member_charges' => ['member_id', 'original_amount_paise'],
        'club_journal_lines' => ['line_number'],
        'club_journal_entries' => ['source_event', 'idempotency_key', 'metadata'],
        'member_payments' => ['payment_date', 'amount_paise', 'idempotency_key'],
        'club_charges' => ['issued_at', 'category'],
    ];

    /** Each update/delete has one positive, organisation-owned row ID. */
    public function corrupt(Club $club, array $mutations): void
    {
        $this->identity();
        if (DB::transactionLevel() !== 0 || DB::connection()->getPdo()->inTransaction()) {
            throw new RuntimeException('Fixture refuses ambient transactions.');
        }
        if ($mutations === [] || count($mutations) > 16) {
            throw new RuntimeException('A bounded fixture mutation plan is required.');
        }
        foreach ($mutations as $m) {
            if (! is_array($m)) throw new RuntimeException('Fixture mutation must be data only.');
            $table = $m['table'] ?? '';
            if (! isset(self::COLUMNS[$table]) || ! is_int($m['id'] ?? null) || $m['id'] < 1
                || array_diff(array_keys($m), ['table', 'id', 'values', 'delete']) !== []
                || (isset($m['delete']) && ($m['delete'] !== true || $table !== 'club_accounting_source_coverage_decisions' || isset($m['values'])))
                || (! isset($m['delete']) && (! is_array($m['values'] ?? null) || $m['values'] === [] || array_diff(array_keys($m['values']), self::COLUMNS[$table]) !== []))) {
                throw new RuntimeException('Fixture mutation is not allowlisted.');
            }
            foreach (($m['values'] ?? []) as $value) {
                if (! is_string($value) && ! is_int($value) && ! is_bool($value) && $value !== null) throw new RuntimeException('Fixture values must be bound scalar data.');
            }
        }
        $before = $this->snapshot();
        $this->expected($before);
        $attemptPdo = null; $transactionId = null;
        try {
            app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $mutations, $before, &$attemptPdo, &$transactionId): void {
                $attemptPdo = DB::connection()->getPdo();
                $transactionId = DB::connection()->selectOne('SELECT pg_current_xact_id()::text AS id', [], false)->id;
                $context->assertUsable($club);
                $this->identity();
                if ($this->snapshot() !== $before) throw new RuntimeException('Fixture catalogue changed before construction.');
                foreach ($mutations as $m) {
                    if (! DB::table($m['table'])->where('club_id', $context->organisationId)->where('id', $m['id'])->lockForUpdate()->first()) {
                        throw new RuntimeException('Fixture target is not owned by the authoritative organisation.');
                    }
                }
                $selected = [];
                foreach ($mutations as $m) if (isset(self::TRIGGERS[$m['table']])) $selected[$m['table']] = self::TRIGGERS[$m['table']];
                ksort($selected);
                foreach ($selected as $table => $trigger) DB::unprepared('ALTER TABLE ONLY public."'.$table.'" DISABLE TRIGGER "'.$trigger.'"');
                // Only this fixed raw-DML loop executes while triggers are disabled.
                foreach ($mutations as $m) {
                    $row = DB::table($m['table'])->where('club_id', $context->organisationId)->where('id', $m['id']);
                    $changed = isset($m['delete']) ? $row->delete() : $row->update($m['values']);
                    if ($changed !== 1) throw new RuntimeException('Fixture must affect exactly one owned row.');
                }
                foreach ($selected as $table => $trigger) DB::unprepared('ALTER TABLE ONLY public."'.$table.'" ENABLE TRIGGER "'.$trigger.'"');
                if ($this->snapshot() !== $before) throw new RuntimeException('Fixture restoration is not proven before commit.');
                $this->expected($before);
                $context->assertUsable($club);
            }, 1);
        } catch (Throwable $failure) {
            try {
                $this->identity();
                if ($transactionId !== null && (DB::connection()->getPdo() !== $attemptPdo || DB::connection()->selectOne('SELECT pg_xact_status(CAST(? AS xid8))::text AS status', [$transactionId], false)->status !== 'aborted')) {
                    throw new RuntimeException('Fixture transaction rollback is not proven by PostgreSQL.');
                }
                if (DB::transactionLevel() !== 0 || DB::connection()->getPdo()->inTransaction() || $this->snapshot() !== $before) {
                    throw new RuntimeException('Rollback/restoration could not be proven.');
                }
            } catch (Throwable $uncertain) {
                $this->discard($uncertain);
            }
            throw $failure;
        }
        try {
            $this->identity();
            if ($this->snapshot() !== $before) throw new RuntimeException('Post-commit restoration is not proven.');
        } catch (Throwable $uncertain) {
            $this->discard($uncertain);
        }
    }

    /** Read-only verification immediately before subsequent business assertions. */
    public function assertRestored(): void
    {
        $this->identity();
        $this->expected($this->snapshot());
    }

    private function identity(): void
    {
        $c = DB::connection();
        if (getenv('RUN_P02B3_FINANCE_POSTGRES') !== '1' || $c->getDriverName() !== 'pgsql'
            || $c->getDatabaseName() !== 'lcb_p02b3_finance_test' || $c->getConfig('username') !== 'lc_beenachi_app'
            || $c->getConfig('host') !== '127.0.0.1' || ! preg_match('/\A[0-9]+\z/', (string) getenv('B3_FIXTURE_PORT'))
            || (string) $c->getConfig('port') !== getenv('B3_FIXTURE_PORT')
            || ! preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', (string) getenv('B3_FIXTURE_STARTED'))
            || ! preg_match('/\A[0-9]+\z/', (string) getenv('B3_FIXTURE_DATABASE_OID'))
            || ! getenv('B3_FIXTURE_POISON_FILE') || is_file(getenv('B3_FIXTURE_POISON_FILE'))) {
            throw new RuntimeException('Disposable fixture identity/opt-in rejected.');
        }
        $r = $c->selectOne("SELECT current_database() AS db, current_user AS role, pg_get_userbyid(d.datdba) AS owner, host(inet_server_addr()) AS host, inet_server_port()::text AS port, extract(epoch FROM pg_postmaster_start_time())::text AS started, d.oid::text AS oid, NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT r.rolinherit AND NOT EXISTS(SELECT 1 FROM pg_auth_members WHERE member=r.oid) AS safe, NOT EXISTS(SELECT 1 FROM pg_database WHERE datname NOT IN ('lcb_p02b3_finance_test','postgres','template0','template1')) AS inventory, NOT EXISTS(SELECT 1 FROM pg_stat_activity WHERE datid=d.oid AND pid<>pg_backend_pid() AND backend_type='client backend') AS isolated FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()", [], false);
        if ($r->db !== 'lcb_p02b3_finance_test' || $r->role !== 'lc_beenachi_app' || $r->owner !== 'lc_beenachi_app'
            || $r->host !== '127.0.0.1' || $r->port !== getenv('B3_FIXTURE_PORT') || $r->started !== getenv('B3_FIXTURE_STARTED')
            || $r->oid !== getenv('B3_FIXTURE_DATABASE_OID') || ! in_array($r->safe, [true,1,'1','t'], true) || ! in_array($r->inventory, [true,1,'1','t'], true) || ! in_array($r->isolated, [true,1,'1','t'], true)) {
            $reason = ['start' => $r->started === getenv('B3_FIXTURE_STARTED'), 'oid' => $r->oid === getenv('B3_FIXTURE_DATABASE_OID'), 'safe' => in_array($r->safe,[true,1,'1','t'],true), 'inventory' => in_array($r->inventory,[true,1,'1','t'],true), 'isolated' => in_array($r->isolated,[true,1,'1','t'],true)];
            throw new RuntimeException('Disposable fixture cluster, owner, role or isolation rejected: '.implode(',', array_keys(array_filter($reason, fn ($v) => ! $v))));
        }
    }

    private function snapshot(): array
    {
        $tables = array_keys(self::COLUMNS);
        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $rows = DB::connection()->select("/* b3 fixture trigger snapshot */ SELECT c.relname AS table_name, t.tgname, t.tgenabled, t.tgisinternal, t.tgtype, t.tgnargs, pg_get_triggerdef(t.oid) AS definition, pg_get_userbyid(c.relowner) AS owner, n.nspname AS schema_name, p.proname, p.prosrc, p.prosecdef, p.proconfig, pg_get_userbyid(p.proowner) AS function_owner FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_proc p ON p.oid=t.tgfoid WHERE n.nspname='public' AND c.relname IN ($placeholders) ORDER BY c.relname,t.tgname", $tables, false);
        $constraints = DB::connection()->select("/* b3 fixture constraint snapshot */ SELECT c.relname, con.conname, con.convalidated, pg_get_constraintdef(con.oid) AS definition FROM pg_constraint con JOIN pg_class c ON c.oid=con.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ($placeholders) ORDER BY c.relname,con.conname", $tables, false);
        return ['triggers' => array_map(fn ($row) => (array) $row, $rows), 'constraints' => array_map(fn ($row) => (array) $row, $constraints)];
    }

    private function expected(array $snapshot): void
    {
        $rows = $snapshot['triggers'];
        foreach ($rows as $row) if ($row['tgenabled'] !== 'O') throw new RuntimeException('Expected fixture trigger definition/enabled state rejected.');
        foreach ($snapshot['constraints'] as $constraint) if (! in_array($constraint['convalidated'], [true,1,'1','t'],true)) throw new RuntimeException('Fixture constraints must remain validated.');
        foreach (self::TRIGGERS as $table => $name) {
            $found = array_values(array_filter($rows, fn ($r) => $r['table_name'] === $table && $r['tgname'] === $name));
            if (count($found) !== 1) throw new RuntimeException('Expected fixture trigger is missing.');
            $t = $found[0];
            $definition = 'CREATE TRIGGER '.$name.' BEFORE DELETE OR UPDATE ON public.'.$table.' FOR EACH ROW EXECUTE FUNCTION accounting_integration_reject_mutation()';
            if ($t['definition'] !== $definition || $t['tgenabled'] !== 'O' || $t['tgisinternal'] || (int) $t['tgtype'] !== 27
                || (int) $t['tgnargs'] !== 0 || $t['owner'] !== 'lc_beenachi_app' || $t['function_owner'] !== 'lc_beenachi_app'
                || $t['proname'] !== 'accounting_integration_reject_mutation' || $t['prosecdef'] || $t['proconfig'] !== null
                || trim($t['prosrc']) !== "BEGIN RAISE EXCEPTION 'accounting integration records are immutable'; END;") {
                throw new RuntimeException('Expected fixture trigger definition/enabled state rejected.');
            }
        }
    }

    private function discard(Throwable $failure): never
    {
        $path = (string) getenv('B3_FIXTURE_POISON_FILE');
        try {
            if ($path === '' || file_put_contents($path, json_encode(['discard' => true, 'database' => 'lcb_p02b3_finance_test', 'reason' => 'Fixture restoration or transaction outcome is uncertain.'], JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Discard marker unavailable.');
        } catch (Throwable) {
            // No marker means later tests cannot safely be admitted: terminate this test process.
            fwrite(STDERR, 'Fixture cluster must be discarded; marker persistence failed.');
            exit(97);
        }
        throw new RuntimeException('Fixture cluster discarded: restoration/outcome uncertain.', 0, $failure);
    }
}
