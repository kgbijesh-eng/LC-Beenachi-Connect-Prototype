<?php
declare(strict_types=1);
namespace Tests\Feature;
use App\Models\{Club,ClubAccountingCutoverPackage,ClubAccountingCutoverScheduleRow,ClubAccountingPeriod,ClubInternalTransfer,ClubMembership,LedgerAccount,Permission,Role,User};
use App\Services\Finance\{ClubInternalTransferService,ClubJournalPostingService,JournalLineInput,JournalPostingRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FinancialPostgresTestCase;

final class Milestone3iizxInternalCashBankTransferTest extends FinancialPostgresTestCase
{
    public function test_authorized_transfer_posts_exact_asset_movement_replays_and_is_tenant_scoped(): void
    {
        $f=$this->fixture('A'); Sanctum::actingAs($f['actor']); $key=(string)Str::uuid(); $payload=$this->payload($f,$key); config(['instance.organisation_code' => $f['club']->code]); $before=$this->getJson($this->statement($f))->assertOk()->json('data.snapshot_fingerprint');
        $response=$this->postJson($this->endpoint($f),$payload)->assertCreated(); $id=$response->json('data.id');
        $this->assertSame($id,$this->postJson($this->endpoint($f),$payload)->assertCreated()->json('data.id'));
        $lines=DB::table('club_journal_lines')->where('journal_entry_id',ClubInternalTransfer::firstOrFail()->journal_entry_id)->orderBy('line_number')->get();
        $this->assertSame([$f['destination']->id,$f['source']->id],$lines->pluck('ledger_account_id')->all()); $this->assertSame(['debit','credit'],$lines->pluck('direction')->all()); $this->assertSame([500000,500000],$lines->pluck('amount_paise')->all()); $this->assertSame(['GENERAL_CLUB_FUND'],$lines->pluck('fund_code')->unique()->values()->all());
        $this->assertDatabaseCount('club_internal_transfers',1); $this->assertDatabaseCount('club_journal_entries',2);
        $statement=$this->getJson($this->statement($f))->assertOk(); $this->assertNotSame($before,$statement->json('data.snapshot_fingerprint')); $this->assertSame(500000,$statement->json('data.overall_trial_balance.period_debits_paise')); $this->assertSame(500000,$statement->json('data.overall_trial_balance.period_credits_paise'));
        $classes=collect($statement->json('data.funds'))->flatMap(fn($fund)=>$fund['classifications']);$accounts=$classes->flatMap(fn($class)=>$class['accounts']);$this->assertSame(500000,$accounts->firstWhere('account_code',$f['source']->code)['closing_balance_paise']);$this->assertSame(500000,$accounts->firstWhere('account_code',$f['destination']->code)['closing_balance_paise']);foreach(['INCOME','EXPENSES'] as $classification){$totals=$classes->firstWhere('classification',$classification)['totals'];$this->assertSame(0,$totals['period_debit_paise']);$this->assertSame(0,$totals['period_credit_paise']);}
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f),[...$payload,'amount_paise'=>500001])->assertConflict()->assertJsonPath('code','TRANSFER_IDEMPOTENCY_CONFLICT');
        $b=$this->fixture('B'); $this->postJson($this->endpoint($f),[...$payload,'idempotency_key'=>(string)Str::uuid(),'destination_account'=>$b['destination']->public_id])->assertUnprocessable();
        config(['instance.organisation_code' => $b['club']->code]);
        $this->getJson("/api/v1/clubs/{$b['club']->id}/finance/transfers/{$id}")->assertForbidden();
    }
    public function test_failure_after_source_creation_rolls_back_source_and_journal(): void
    {
        $f=$this->fixture('D'); DB::unprepared("CREATE TRIGGER reject_transfer_journal BEFORE INSERT ON club_journal_entries WHEN NEW.source_type = 'club_internal_transfer' BEGIN SELECT RAISE(ABORT, 'controlled transfer failure'); END");
        try { app(ClubInternalTransferService::class)->record($f['club'],$f['actor'],$this->payload($f,(string)Str::uuid())); $this->fail('Controlled journal failure must propagate.'); } catch (\Throwable) { $this->assertSame(0,ClubInternalTransfer::query()->where('club_id',$f['club']->id)->count()); }
        $this->assertSame(0,DB::table('club_journal_entries')->where('club_id',$f['club']->id)->where('source_type',ClubInternalTransferService::SOURCE_TYPE)->count());
    }
    public function test_exact_replay_rejects_material_persisted_transfer_provenance_corruption():void
    {
        $f=$this->fixture('C');Sanctum::actingAs($f['actor']);$key=(string)Str::uuid();$payload=$this->payload($f,$key);config(['instance.organisation_code' => $f['club']->code]); $this->postJson($this->endpoint($f),$payload)->assertCreated();$transfer=ClubInternalTransfer::where('club_id',$f['club']->id)->firstOrFail();$journal=DB::table('club_journal_entries')->where('id',$transfer->journal_entry_id)->first();$debit=DB::table('club_journal_lines')->where('journal_entry_id',$transfer->journal_entry_id)->where('direction','debit')->first();
        $cases=[
            [['journal_entry_id'=>null],['journal_entry_id'=>$transfer->journal_entry_id],'club_internal_transfers'],
            [['source_type'=>'corrupt'],['source_type'=>$journal->source_type],'club_journal_entries'],
            [['source_id'=>(string)Str::uuid()],['source_id'=>$journal->source_id],'club_journal_entries'],
            [['source_event'=>'corrupt'],['source_event'=>$journal->source_event],'club_journal_entries'],
            [['idempotency_key'=>(string)Str::uuid()],['idempotency_key'=>$journal->idempotency_key],'club_journal_entries'],
            [['ledger_account_id'=>$transfer->source_account_id],['ledger_account_id'=>$debit->ledger_account_id],'club_journal_lines'],
            [['direction'=>'credit'],['direction'=>$debit->direction],'club_journal_lines'],
            [['amount_paise'=>$transfer->amount_paise+1],['amount_paise'=>$debit->amount_paise],'club_journal_lines'],
            [['fund_code'=>'SERVICE_ACTIVITY_FUND'],['fund_code'=>$debit->fund_code],'club_journal_lines'],
        ];
        foreach($cases as [$corrupt,$restore,$table]){$id=$table==='club_internal_transfers'?$transfer->id:($table==='club_journal_entries'?$transfer->journal_entry_id:$debit->id);DB::table($table)->where('id',$id)->update($corrupt);config(['instance.organisation_code' => $f['club']->code]); $this->postJson($this->endpoint($f),$payload)->assertConflict()->assertJsonPath('code','TRANSFER_IDEMPOTENCY_CONFLICT');DB::table($table)->where('id',$id)->update($restore);}
    }
    public function test_invalid_accounts_self_transfer_period_and_authority_fail_closed(): void
    {
        $f=$this->fixture('C'); Sanctum::actingAs($f['actor']);
        config(['instance.organisation_code' => $f['club']->code]);
        foreach ([['destination_account'=>$f['source']->public_id],['source_account'=>$f['expense']->public_id],['amount_paise'=>0]] as $change) $this->postJson($this->endpoint($f),[...$this->payload($f,(string)Str::uuid()),...$change])->assertUnprocessable();
        DB::table('ledger_accounts')->where('id',$f['destination']->id)->update(['is_active'=>false]); $this->postJson($this->endpoint($f),$this->payload($f,(string)Str::uuid()))->assertUnprocessable(); DB::table('ledger_accounts')->where('id',$f['destination']->id)->update(['is_active'=>true]);
        DB::table('club_accounting_periods')->where('id',$f['period']->id)->update(['status'=>'closed','closed_at'=>now(),'closed_by_user_id'=>$f['actor']->id]); $this->postJson($this->endpoint($f),$this->payload($f,(string)Str::uuid()))->assertUnprocessable();
        DB::table('club_memberships')->where('club_id',$f['club']->id)->where('user_id',$f['actor']->id)->update(['status'=>'inactive']); $this->postJson($this->endpoint($f),$this->payload($f,(string)Str::uuid()))->assertForbidden();
        $this->assertSame(0,ClubInternalTransfer::count());
    }
    private function fixture(string $suffix): array
    {
        $club=Club::create(['name'=>"Transfer {$suffix}",'code'=>"TR{$suffix}",'timezone'=>'Asia/Kolkata','is_active'=>true]);config(['instance.organisation_code'=>$club->code]);$actor=User::factory()->create();$permissions=collect(['finance.club.transfers.create','finance.club.statement.view'])->map(fn($slug)=>Permission::firstOrCreate(['slug'=>$slug],['name'=>$slug,'group'=>'finance','description'=>$slug]));$role=Role::create(['name'=>"Treasurer {$suffix}",'slug'=>"treasurer-".strtolower($suffix),'description'=>'Treasurer']);$role->permissions()->attach($permissions->pluck('id'));ClubMembership::create(['club_id'=>$club->id,'user_id'=>$actor->id,'role_id'=>$role->id,'status'=>'active']);
        $period=ClubAccountingPeriod::create(['club_id'=>$club->id,'lions_year'=>'2027-28','start_date'=>'2027-07-01','end_date'=>'2028-06-30','status'=>'open','created_by_user_id'=>$actor->id]);$source=$this->account($club,$actor,"CASH-{$suffix}",'asset','CASH_AND_BANK');$destination=$this->account($club,$actor,"BANK-{$suffix}",'asset','CASH_AND_BANK');$equity=$this->account($club,$actor,"EQUITY-{$suffix}",'equity','FUND_BALANCE_EQUITY');$expense=$this->account($club,$actor,"EXPENSE-{$suffix}",'expense','EXPENSES');
        $package=ClubAccountingCutoverPackage::create(['club_id'=>$club->id,'lions_year'=>'2027-28','cutover_date'=>'2027-07-01','chart_version'=>'CLUB-COA-2026-01','package_version'=>1,'status'=>'draft','currency'=>'INR','canonical_checksum'=>str_repeat('a',64),'reconciliation_difference_paise'=>0,'dr033_classification'=>'OPENING-BALANCE-EQUITY','activation_ready'=>false,'created_by_user_id'=>$actor->id,'updated_by_user_id'=>$actor->id]);$rows=collect([[$source,'debit'],[$equity,'credit']])->map(fn($row)=>ClubAccountingCutoverScheduleRow::create(['club_id'=>$club->id,'cutover_package_id'=>$package->id,'schedule_type'=>$row[0]->account_type==='equity'?'equity_preview':'cash_on_hand','row_key'=>'OPENING-'.$row[0]->id,'non_sensitive_label'=>$row[0]->name,'ledger_account_id'=>$row[0]->id,'direction'=>$row[1],'amount_paise'=>1000000,'currency'=>'INR','fund_code'=>'GENERAL_CLUB_FUND']));$opening=app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id,$period->id,'2027-07-01','Opening','INR','opening_balance_activation',$package->public_id,'activated',(string)Str::uuid(),$actor->id,[new JournalLineInput($source->id,'debit',1000000,fundCode:'GENERAL_CLUB_FUND',openingScheduleRowId:$rows[0]->id),new JournalLineInput($equity->id,'credit',1000000,fundCode:'GENERAL_CLUB_FUND',openingScheduleRowId:$rows[1]->id)]));DB::table('club_accounting_cutover_packages')->where('id',$package->id)->update(['status'=>'activation_authorized','activation_ready'=>true]);DB::table('club_accounting_cutover_packages')->where('id',$package->id)->update(['status'=>'posted','opening_journal_entry_id'=>$opening->id,'posted_at'=>now(),'posted_by_user_id'=>$actor->id]);return compact('club','actor','period','source','destination','expense');
    }
    private function account(Club $club,User $actor,string $code,string $type,string $classification): LedgerAccount{return LedgerAccount::create(['club_id'=>$club->id,'code'=>$code,'name'=>$code,'account_type'=>$type,'normal_balance'=>$type==='asset'||$type==='expense'?'debit':'credit','currency'=>'INR','is_active'=>true,'created_by_user_id'=>$actor->id,'origin_kind'=>'custom','statement_classification'=>$classification]);}
    private function payload(array $f,string $key):array{return ['accounting_date'=>'2027-08-10','source_account'=>$f['source']->public_id,'destination_account'=>$f['destination']->public_id,'fund'=>'GENERAL_CLUB_FUND','amount_paise'=>500000,'description'=>'Deposit cash','external_reference'=>'TR-1','idempotency_key'=>$key];}
    private function endpoint(array $f):string{return "/api/v1/clubs/{$f['club']->id}/finance/transfers";}
    private function statement(array $f):string{return "/api/v1/clubs/{$f['club']->id}/finance/account-statement?lions_year=2027-28";}
}
