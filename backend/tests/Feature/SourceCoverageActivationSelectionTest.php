<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\User;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\IntegrationConfigurationFixture;
use Tests\Support\SourceCoverageFixture as Fixture;

class SourceCoverageActivationSelectionTest extends FinancialPostgresTestCase
{
    public static function boundaries(): array
    {
        $cases = [];
        foreach (['member_charge', 'member_payment'] as $type) {
            foreach (['2027-08-12', '2027-08-13', '2027-08-14'] as $date) {
                foreach (['active', 'reactivated'] as $state) {
                    $cases[$type.'-'.$date.'-'.$state] = [$type, $date, $state];
                }
            }
        }

return $cases;
    }

    #[DataProvider('boundaries')]
    public function test_new_decision_uses_current_generation_and_inclusive_date_boundary(string $type, string $date, string $state): void
    {
        $p = Fixture::prepare($type, $state, '2027-08-13');
        $p['source_date'] = $date;
        $p['commit_date'] = $date;
        $before = ClubJournalEntry::where('club_id', $p['club'])->count();
        $source = Fixture::perform($p);
        $decision = Fixture::decision($p);
        $required = $date >= '2027-08-13';
        $this->assertSame($required, (bool) $decision->integration_required);
        $this->assertSame($p['activation'], $decision->integration_activation_id);
        $this->assertNull($decision->terminal_activation_event_id);
        $this->assertSame($required ? 'integration_required' : 'before_coverage', $decision->decision_reason);
        $this->assertSame($before + (int) $required, ClubJournalEntry::where('club_id', $p['club'])->count());
        if ($required) {
            $integration = ClubAccountingSourceIntegration::where('club_id', $p['club'])->where('source_type', $type)->firstOrFail();
            $this->assertSame($p['activation'], $integration->integration_activation_id);
            $this->assertSame($p['mapping_version'], $integration->mapping_version);
            $this->assertTrue(ClubJournalLine::where('journal_entry_id', $integration->journal_entry_id)->where('ledger_account_id', $p['mapped_account'])->exists());
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function histories(): array
    {
        $cases = [];
        foreach (['member_charge', 'member_payment'] as $type) {
            foreach (['none', 'terminal', 'terminal-before', 'multiple-terminal', 'missing-slot', 'ambiguous'] as $state) {
                $cases[$type.'-'.$state] = [$type, $state];
            }
        }

return $cases;
    }

    #[DataProvider('histories')]
    public function test_no_current_history_and_inconsistent_states_have_no_arbitrary_fallback(string $type, string $state): void
    {
        $p = Fixture::prepare($type, in_array($state, ['missing-slot', 'terminal-before'], true) ? 'terminal' : ($state === 'ambiguous' ? 'active' : $state), $state === 'terminal-before' ? '2027-08-13' : '2027-07-01');
        if (in_array($state, ['missing-slot', 'ambiguous'], true)) {
            $copy = ClubAccountingIntegrationActivation::findOrFail($p['activation'])->replicate();
            $copy->public_id = (string) Str::uuid();
            $copy->activation_reference = 'ORPHAN-'.Str::uuid();
            $copy->save();
        }
        $before = IntegrationConfigurationFixture::snapshot($p['club']);
        if (in_array($state, ['multiple-terminal', 'missing-slot', 'ambiguous'], true)) {
            try {
                Fixture::perform($p);
                $this->fail('Ambiguous/inconsistent new coverage must fail closed.');
            } catch (AccountingIntegrationException $e) {
                $this->assertStringContainsString('ambiguous', $e->getMessage());
            }
            $this->assertSame($before, IntegrationConfigurationFixture::snapshot($p['club']));
            $this->assertNull(Fixture::decision($p));
        } else {
            Fixture::perform($p);
            $d = Fixture::decision($p);
            $this->assertFalse((bool) $d->integration_required);
            $this->assertSame($state === 'none' ? 'not_activated' : ($state === 'terminal-before' ? 'before_coverage' : 'after_terminal_event'), $d->decision_reason);
            $this->assertSame($state === 'none' ? null : $p['activation'], $d->integration_activation_id);
            $this->assertSame($before['club_journal_entries'], IntegrationConfigurationFixture::snapshot($p['club'])['club_journal_entries']);
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_backdated_payment_does_not_resurrect_the_old_generation(): void
    {
        $p = Fixture::prepare('member_payment', 'active');
        Fixture::revoke($p);
        Fixture::activateNext($p, 'SC-V2', '2027-08-13');
        $p['source_date'] = '2027-08-12';
        $p['commit_date'] = '2027-08-14';
        Fixture::perform($p);
        $d = Fixture::decision($p);
        $this->assertSame($p['activation'], $d->integration_activation_id);
        $this->assertFalse((bool) $d->integration_required);
        $this->assertSame('before_coverage', $d->decision_reason);
    }

    public static function historical(): array
    {
        $cases = [];
        foreach (['member_charge', 'member_payment'] as $type) {
            foreach (['active', 'terminal'] as $state) {
                $cases[$type.'-'.$state] = [$type, $state];
            }
        }

return $cases;
    }

    #[DataProvider('historical')]
    public function test_replay_and_correction_keep_persisted_coverage_after_two_terminal_generations(string $type, string $state): void
    {
        $p = Fixture::prepare($type, $state);
        $source = Fixture::perform($p);
        $decision = (array) Fixture::decision($p);
        if ($state === 'active') {
            Fixture::revoke($p);
        }
        $oldMapping = ClubAccountingSourceMapping::where('club_id', $p['club'])->where('source_type', $type)->where('mapping_version', $p['mapping_version'])->firstOrFail();
        app(AccountingIntegrationConfigurationService::class)->revokeMapping($oldMapping, User::findOrFail($p['actor']), 'HIST-MAP-'.Str::uuid(), 'Historical mapping revocation');
        Fixture::activateNext($p, 'SC-V2', '2027-08-15');
        $liveSnapshot = IntegrationConfigurationFixture::snapshot($p['club']);
        $this->assertSame($source->id, Fixture::perform($p)->id);
        $this->assertSame($liveSnapshot, IntegrationConfigurationFixture::snapshot($p['club']));
        Fixture::revoke($p);
        $before = IntegrationConfigurationFixture::snapshot($p['club']);
        $this->assertSame($source->id, Fixture::perform($p)->id);
        $this->assertSame($before, IntegrationConfigurationFixture::snapshot($p['club']));
        $changed = $p;
        if ($type === 'member_payment') {
            $changed['payment_input']['amount_paise'] = 101;
        } else {
            $changed['version'] += 10;
        }
        try {
            Fixture::perform($changed);
            $this->fail('Changed historical replay must conflict.');
        } catch (ConflictHttpException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($before, IntegrationConfigurationFixture::snapshot($p['club']));
        $journals = ClubJournalEntry::where('club_id', $p['club'])->count();
        Fixture::correct($p, $source);
        $this->assertSame($journals + ($state === 'active' ? 1 : 0), ClubJournalEntry::where('club_id', $p['club'])->count());
        $this->assertSame($decision, (array) Fixture::decision($p));
        if ($type === 'member_payment') {
            $after = IntegrationConfigurationFixture::snapshot($p['club']);
            $this->assertSame($source->id, Fixture::perform($p)->id);
            $this->assertSame($after, IntegrationConfigurationFixture::snapshot($p['club']));
        }
    }

    public static function failures(): array
    {
        $cases = [];
        foreach (['member_charge', 'member_payment'] as $type) {
            foreach (['coverage', 'posting', 'linkage', 'audit'] as $point) {
                $cases[$type.'-'.$point] = [$type, $point];
            }
        }

return $cases;
    }

    #[DataProvider('failures')]
    public function test_failure_after_each_stage_rolls_back_the_entire_source_transaction(string $type, string $point): void
    {
        $p = Fixture::prepare($type, 'reactivated');
        $before = IntegrationConfigurationFixture::snapshot($p['club']);
        $fired = false;
        $fail = function () use (&$fired): void {
            $fired = true;
            throw new RuntimeException('controlled coverage-stage failure');
        };
        if ($point === 'coverage') {
            DB::listen(function ($q) use ($fail): void {
                if (str_starts_with($q->sql, 'insert into "club_accounting_source_coverage_decisions"')) {
                    $fail();
                }
            });
        } else {
            $class = match ($point) {
                'posting' => ClubJournalEntry::class,'linkage' => ClubAccountingSourceIntegration::class,default => AuditLog::class
            };
            $class::created($fail);
        }
        try {
            Fixture::perform($p);
            $this->fail('Injected failure must escape.');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled coverage-stage failure', $e->getMessage());
        }
        $this->assertTrue($fired);
        $this->assertSame($before, IntegrationConfigurationFixture::snapshot($p['club']));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_current_slot_guards_reject_terminal_foreign_and_wrong_source_bindings(): void
    {
        $p = Fixture::prepare('member_charge', 'terminal');
        $slot = ['club_id' => $p['club'], 'source_type' => 'member_charge', 'integration_activation_id' => $p['activation'], 'created_at' => now(), 'updated_at' => now()];
        $copy = ClubAccountingIntegrationActivation::findOrFail($p['activation'])->replicate();
        $copy->public_id = (string) Str::uuid();
        $copy->activation_reference = 'UNSLOTTED-'.Str::uuid();
        $copy->save();
        $foreign = Club::create(['name' => 'Foreign slot', 'code' => 'FOREIGN']);
        foreach ([$slot, [...$slot, 'integration_activation_id' => $copy->id, 'source_type' => 'member_payment'], [...$slot, 'integration_activation_id' => $copy->id, 'club_id' => $foreign->id]] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('club_accounting_integration_current_activations')->insert($invalid), 1);
                $this->fail('Invalid current binding must be rejected by existing database guards.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->count());
    }

    public static function types(): array
    {
        return [['member_charge'], ['member_payment']];
    }

    #[DataProvider('types')]
    public function test_third_current_generation_wins_over_all_terminal_history(string $type): void
    {
        $p = Fixture::prepare($type, 'multiple-terminal');
        Fixture::activateNext($p, 'SC-V3', '2027-07-01');
        Fixture::perform($p);
        $d = Fixture::decision($p);
        $this->assertTrue((bool) $d->integration_required);
        $this->assertSame($p['activation'], $d->integration_activation_id);
        $integration = ClubAccountingSourceIntegration::where('club_id', $p['club'])->where('source_type', $type)->firstOrFail();
        $this->assertSame('SC-V3', $integration->mapping_version);
        $this->assertSame($p['activation'], $integration->integration_activation_id);
    }

    #[DataProvider('types')]
    public function test_outer_rollback_restores_trigger_deleted_slot_and_nested_event(string $type): void
    {
        $p = Fixture::prepare($type);
        $before = IntegrationConfigurationFixture::snapshot($p['club']);
        try {
            app(OrganisationTransactionGuard::class)->run(Club::findOrFail($p['club']), function ($context) use ($p): void {
                app(AccountingIntegrationConfigurationService::class)->revokeActivationWithin($context, ClubAccountingIntegrationActivation::findOrFail($p['activation']), User::findOrFail($p['actor']), 'ROLLBACK-'.Str::uuid(), 'Synthetic rollback');
                $this->assertSame(0, DB::table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->where('source_type', $p['source_type'])->count());
                throw new RuntimeException('controlled parent rollback');
            }, 1);
            $this->fail('Parent failure must escape.');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled parent rollback',$e->getMessage());
        }
        $this->assertSame($before,IntegrationConfigurationFixture::snapshot($p['club']));
        Fixture::perform($p);
        $this->assertTrue((bool) Fixture::decision($p)->integration_required);
    }
}
