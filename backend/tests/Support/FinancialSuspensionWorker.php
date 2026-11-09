<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\ClubMembership;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\AccountingPeriodCloseRequest;
use App\Services\Finance\ClubAccountingPeriodCloseService;
use App\Services\Finance\Exceptions\JournalPostingException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialWorkerSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$requestPath, $dir, $name] = array_slice($argv, 1);
$p = json_decode(file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$await = function (string $file): void {
    $end = microtime(true) + 20;
    while (! is_file($file)) {
        if (microtime(true) >= $end) {
            throw new RuntimeException('Financial race barrier timed out.');
        } usleep(5000);
    }
};
try {
    FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
    file_put_contents($dir.'/'.$name.'.ready', json_encode(['pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid], JSON_THROW_ON_ERROR));
    $await($dir.'/'.$name.'.start');
    if (($p['pause'] ?? '') === 'before-insert') {
        DB::connection()->beforeExecuting(function ($sql) use ($dir, $name, $await): void {
            if (str_starts_with($sql, 'insert into "payments"')) {
                file_put_contents($dir.'/'.$name.'.locked', '1');
                $await($dir.'/'.$name.'.finish');
            }
        });
    }
    $held = false;
    DB::listen(function ($query) use (&$held, $p, $dir, $name, $await): void {
        if ($p['hold'] && ($p['pause'] ?? '') !== 'before-insert' && ! $held && str_contains($query->sql, '"clubs"') && str_contains($query->sql, 'for update')) {
            if (DB::transactionLevel() !== 1) {
                throw new RuntimeException('Club lock was not acquired in the outer root.');
            }
            $held = true;
            file_put_contents($dir.'/'.$name.'.locked', '1');
            $await($dir.'/'.$name.'.finish');
        }
    });
    if ($p['mode'] === 'suspend') {
        // Test control operation only; never an operator command or production service.
        DB::transaction(function () use ($p, $dir, $name, $await): void {
            if (Club::whereKey($p['club'])->update(['is_active' => false]) !== 1) {
                throw new RuntimeException('Synthetic suspension target missing.');
            }
            if ($p['hold']) {
                file_put_contents($dir.'/'.$name.'.locked', '1');
                $await($dir.'/'.$name.'.finish');
            }
        }, 1);
    } elseif ($p['mode'] === 'operation') {
        FinancialOperationFixture::perform($p);
    } elseif ($p['mode'] === 'close') {
        app(ClubAccountingPeriodCloseService::class)->close(new AccountingPeriodCloseRequest($p['club'], $p['period'], $p['actor'], '2027-28', 'B3 period-close compatibility'));
    } elseif (in_array($p['mode'], ['historical-profile', 'historical-member'], true)) {
        DB::transaction(function () use ($p, $dir, $name, $await): void {
            if ($p['mode'] === 'historical-profile') {
                App\Models\ProfileUpdateRequest::whereKey($p['profile_request'])->lockForUpdate()->firstOrFail();
                App\Models\Member::whereKey($p['member'])->lockForUpdate()->firstOrFail();
                file_put_contents($dir.'/'.$name.'.locked', '1');
                $await($dir.'/'.$name.'.finish');
                App\Models\AuditLog::create([
                    'club_id' => $p['club'],
                    'user_id' => $p['actor'],
                    'action' => 'diagnostic.legacy_profile_lock_order',
                    'auditable_type' => App\Models\ProfileUpdateRequest::class,
                    'auditable_id' => $p['profile_request'],
                ]);
            } else {
                Club::whereKey($p['club'])->lockForUpdate()->firstOrFail();
                App\Models\Member::whereKey($p['member'])->lockForUpdate()->firstOrFail();
            }
        }, 1);
    } elseif (str_starts_with($p['mode'], 'profile-')) {
        $service = app(App\Services\ProfileUpdates\ProfileUpdateWriteService::class);
        $club = Club::findOrFail($p['club']);
        $user = App\Models\User::findOrFail($p['profile_user']);
        $operation = substr($p['mode'], strlen('profile-'));
        if ($operation === 'submit') {
            $service->submit($club, $user, ['changes' => ['occupation' => 'Proposed']]);
        } else {
            $profile = App\Models\ProfileUpdateRequest::findOrFail($p['profile_request']);
            if ($operation === 'reject') {
                $service->reject($club, $user, $profile, 1, 'Synthetic review');
            } else {
                $service->$operation($club, $user, $profile, 1);
            }
        }
    } elseif (in_array($p['mode'], ['member-update', 'member-link', 'member-import'], true)) {
        $base = '/api/v1/clubs/'.$p['club'].'/members/';
        $import = $p['mode'] === 'member-import';
        $data = match ($p['mode']) {
            'member-import' => ['rows' => [['member_code' => 'PROBE', 'full_name' => 'Synthetic financial member', 'status' => 'inactive']]],
            'member-link' => ['user_id' => $p['actor']],
            default => ['status' => 'inactive'],
        };
        $request = Request::create($base.($import ? 'import-commit' : $p['member']), $import ? 'POST' : 'PATCH', $data, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$p['writer_token'], 'HTTP_ACCEPT' => 'application/json']);
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Authorised member writer HTTP status '.$response->getStatusCode().': '.$response->getContent());
        }
    } elseif ($p['mode'] === 'membership-update') {
        ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->update(['status' => 'inactive']);
    } else {
        throw new RuntimeException('Unknown financial race mode.');
    }
    $result = ['status' => 'success', 'depth' => DB::transactionLevel()];
} catch (OrganisationAccessRejected $e) {
    $result = ['status' => 'rejected', 'reason' => $e->reason, 'depth' => DB::transactionLevel()];
} catch (JournalPostingException|App\Services\Finance\Exceptions\JournalReversalException|App\Services\Finance\Exceptions\ClubExpenseException|App\Services\Finance\Exceptions\ClubOrdinaryIncomeReceiptCorrectionException|Illuminate\Validation\ValidationException|AuthorizationException $e) {
    $result = ['status' => 'domain_rejected', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
    $result = str_starts_with($p['mode'], 'profile-')
        ? ['status' => 'http_'.$e->getStatusCode(), 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()]
        : ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
} catch (Illuminate\Database\QueryException $e) {
    $result = str_starts_with($p['mode'], 'historical-') && $e->getCode() === '40P01'
        ? ['status' => '40P01', 'depth' => DB::transactionLevel()]
        : ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
} catch (Throwable $e) {
    $result = ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
}
file_put_contents($dir.'/'.$name.'.result.json', json_encode($result, JSON_THROW_ON_ERROR));
exit($result['status'] === 'error' ? 1 : 0);
