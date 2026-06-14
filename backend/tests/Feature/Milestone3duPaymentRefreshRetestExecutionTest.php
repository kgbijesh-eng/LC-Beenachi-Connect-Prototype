<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PaymentRecordingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class Milestone3duPaymentRefreshRetestExecutionTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__.'/../../docs/milestone_3du_payment_refresh_retest_execution.md';

    public function test_3du_creates_payment_refresh_retest_execution_document(): void
    {
        $document = $this->retestDocument();

        foreach ([
            'Milestone 3DU — Payment Refresh Retest Execution',
            '3DU must perform a narrow targeted retest of DN-047 only.',
            '3DU must not implement fixes.',
            '3DU must not edit runtime code.',
            '3DU must not edit frontend runtime code.',
            '3DU must not edit app/page.tsx.',
            '3DU must not change backend runtime behavior.',
            '3DU must not edit payment controllers, payment services, routes, requests, resources, models, migrations, seeders, factories, policies, middleware, config, package files, or lock files.',
            '3DU must not fix DN-020.',
            '3DU must not perform a new full manual acceptance execution.',
            '3DU must not deploy.',
            '3DU must not use production data.',
            '3DU must not use real club financial records.',
            '3DU must not perform real payment collection.',
            'Relationship to corrected 3DN',
            'Relationship to 3DO',
            'Relationship to 3DP',
            'Relationship to 3DQ',
            'Relationship to 3DR',
            'Relationship to 3DS',
            'Relationship to 3DT',
            'Relationship to finance/payment lanes',
            'DN-010 fixed by 3DQ',
            'DN-031 fixed by 3DS',
            'DN-047 observed failure',
            'retest method',
            'retest data',
            'retest steps',
            'retest result',
            'DN-047 final classification',
            'No runtime fix was implemented in 3DU.',
            'DN-020 remains unresolved.',
            'GO/NO-GO interpretation',
            'no new full manual acceptance',
            'no production data',
            'no real payment collection',
            'no deployment',
            'unsupported feature',
            'receipt PDF',
            'profile document',
            'risk notes',
            'retest decision',
            'expected next milestone',
        ] as $expectedText) {
            $this->assertStringContainsStringIgnoringCase($expectedText, $document);
        }
    }

    public function test_3du_current_file_changes_are_limited_to_approved_retest_execution_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3du_payment_refresh_retest_execution.md',
            'backend/tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php',
            'backend/tests/Feature/Milestone3dtPaymentRefreshRetestScopeGateTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DU may change only approved 3DU docs/tests, plus the narrow 3DT lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DU.");
        }
    }

    public function test_3du_app_page_tsx_is_not_edited(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $diff = shell_exec('git -C '.escapeshellarg($repoRoot).' diff --name-only HEAD') ?? '';
        $changed = collect(preg_split('/\R/', trim($diff)))->filter()->all();
        $this->assertNotContains('app/page.tsx', $changed);
    }

    public function test_targeted_dn_047_payment_refresh_retest_execution(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'LCB-3DU-RETEST-01',
            'full_name' => '3DU Retest Member',
            'status' => 'active',
            'phone' => '8888888888',
            'email' => '3du.retest@lcbeenachi.test',
        ]);

        MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => User::where('email', 'president@lcbeenachi.test')->firstOrFail()->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_MONTHLY_DUES,
            'amount' => 500.00,
            'accounting_month' => '2026-06',
            'due_date' => '2026-06-10',
            'transaction_date' => '2026-06-10',
            'description' => '3DU synthetic due item',
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        $presidentLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'president@lcbeenachi.test',
            'password' => 'password',
        ]);
        $presidentLogin->assertOk();
        $token = $presidentLogin->json('token');

        $summaryBefore = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary");

        $summaryBefore->assertOk()
            ->assertJsonPath('finance_summary.current_dues_amount', '500.00');

        $balanceBefore = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance");

        $balanceBefore->assertOk()
            ->assertJsonPath('balance.balance', '500.00');

        $historyBefore = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history");

        $historyBefore->assertOk()
            ->assertJsonPath('meta.total', 0);

        $recordPaymentResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '350.00',
                'payment_date' => '2026-06-14',
                'payment_mode' => 'cash',
                'reference_number' => '3DU-RETEST-REF-01',
                'notes' => '3DU DN-047 targeted retest payment recording',
            ]);

        $recordPaymentResponse->assertCreated()
            ->assertJsonPath('payment.member_id', $member->id)
            ->assertJsonPath('payment.amount', '350.00')
            ->assertJsonPath('payment.status', 'posted');

        $summaryAfter = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/finance-summary");

        $summaryAfter->assertOk()
            ->assertJsonPath('finance_summary.current_dues_amount', '150.00');

        $balanceAfter = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance");

        $balanceAfter->assertOk()
            ->assertJsonPath('balance.balance', '150.00');

        $historyAfter = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history");

        $historyAfter->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('payment_history.0.amount', '350.00');

        Auth::guard('sanctum')->forgetUser();

        $normalMemberLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'local.member@lcbeenachi.test',
            'password' => 'password',
        ]);
        $normalMemberLogin->assertOk();
        $normalToken = $normalMemberLogin->json('token');

        $blockedPayment = $this
            ->withHeader('Authorization', 'Bearer '.$normalToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments", [
                'amount' => '100.00',
                'payment_date' => '2026-06-14',
                'payment_mode' => 'cash',
            ]);

        $blockedPayment->assertForbidden();
    }

    private function retestDocument(): string
    {
        $this->assertFileExists($this->docPath);
        return file_get_contents($this->docPath);
    }

    private function blockedPaths(): array
    {
        return [
            'app/page.tsx',
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
