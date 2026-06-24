<?php
namespace Tests\Feature;
use Tests\TestCase;
class Milestone3gsFinanceFrontendIntegrationTest extends TestCase {
 public function test_frontend_is_backend_confirmed_paise_based_role_safe_and_truthful():void {
  $component=file_get_contents(dirname(__DIR__,3).'/app/FinanceBackend.tsx');$page=file_get_contents(dirname(__DIR__,3).'/app/page.tsx');$doc=file_get_contents(__DIR__.'/../../docs/milestone_3gs_finance_frontend_integration.md');
  foreach(['assessed_display','paid_display','outstanding_display','member_charge_id','idempotency_key:crypto.randomUUID()','Confirm backend submission','Backend confirmed payment and receipt.','function clear()','setSummary(null)','setReceipt(null)','Financial Reports disabled','role!=="member"','memberId={member.backendId}','150000 assessed paise','40000 paid paise','110000 outstanding paise','lc_beenachi_3gs_finance_isolated.sqlite'] as $x)$this->assertStringContainsString($x,$component.$page.$doc);
  $this->assertStringContainsString('import FinanceBackend from "./FinanceBackend";',$page);
  $this->assertStringNotContainsString('collections-summary',$component);
  $this->assertStringNotContainsString('localStorage',$component);
  $this->assertStringNotContainsString('parseFloat',$component);
 }
}
