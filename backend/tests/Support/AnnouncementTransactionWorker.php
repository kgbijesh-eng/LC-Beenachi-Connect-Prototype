<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Announcements\ClubAnnouncementReadStateService;
use App\Services\Announcements\ClubAnnouncementWriteService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\FinancialWorkerSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$path, $dir, $name] = array_slice($argv, 1);
$p = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

$await = static function (string $file): void {
    $end = microtime(true) + 25;
    while (! is_file($file)) {
        if (microtime(true) > $end) {
            throw new RuntimeException('Announcement race barrier timed out.');
        }
        usleep(5000);
    }
};

try {
    FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
    DB::statement("SET lock_timeout = '20s'");
    file_put_contents("$dir/$name.ready", (string) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid);
    $await("$dir/$name.start");
    $held = false;
    DB::listen(function ($query) use (&$held, $p, $dir, $name, $await): void {
        if ($p['hold'] && ! $held && str_contains($query->sql, '"clubs"') && str_contains($query->sql, 'for update')) {
            if (DB::transactionLevel() !== 1) {
                throw new RuntimeException('Expected an outer club lock.');
            }
            $held = true;
            file_put_contents("$dir/$name.locked", '1');
            $await("$dir/$name.finish");
        }
    });

    $club = Club::findOrFail($p['club']);
    $user = User::findOrFail($p['user']);
    $operation = $p['operation'];
    $status = 'ok';
    if ($operation === 'suspend') {
        DB::transaction(function () use ($club): void {
            $current = Club::whereKey($club->id)->lockForUpdate()->firstOrFail();
            $current->update(['is_active' => false]);
        });
    } elseif ($operation === 'membership') {
        app(OrganisationTransactionGuard::class)->run($club, function () use ($p, $club): void {
            $membership = ClubMembership::where('club_id', $club->id)->where('user_id', $p['target_user'])->lockForUpdate()->firstOrFail();
            $membership->update($p['membership_update']);
        });
    } elseif (isset($p['route'])) {
        $request = Request::create($p['route'], $p['method'], $p['payload'] ?? [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$p['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $status = 'http_'.$response->getStatusCode();
    } else {
        $service = app(ClubAnnouncementWriteService::class);
        $announcement = isset($p['announcement']) ? ClubAnnouncement::findOrFail($p['announcement']) : null;
        $version = $p['version'] ?? 1;
        $status = match ($operation) {
            'create' => $service->createDraft($club, $user, $p['input']),
            'edit' => $service->updateDraft($club, $announcement, $user, $version, ['title' => 'Edited notice']),
            'replace' => $service->createReplacementDraft($club, $announcement, $user, $p['input']),
            'approve' => $service->approve($club, $announcement, $user, $version),
            'reject' => $service->rejectApproval($club, $announcement, $user, $version, 'Synthetic decision'),
            'revoke' => $service->revokeApproval($club, $announcement, $user, $version, ClubAnnouncementPublicationDecision::findOrFail($p['approval']), 'Synthetic decision'),
            'publish' => $service->publish($club, $announcement, $user, $version),
            'withdraw' => $service->withdraw($club, $announcement, $user, $version, 'Synthetic withdrawal'),
            'archive' => $service->archive($club, $announcement, $user, $version),
            'mark' => app(ClubAnnouncementReadStateService::class)->markRead($club, $announcement, $user),
            default => throw new RuntimeException('Unknown announcement race operation.'),
        } === null ? 'ineligible' : 'ok';
    }
    $result = ['status' => $status];
} catch (OrganisationAccessRejected $e) {
    $result = ['status' => $e->reason];
} catch (\Illuminate\Auth\Access\AuthorizationException) {
    $result = ['status' => 'http_403'];
} catch (HttpExceptionInterface $e) {
    $result = ['status' => 'http_'.$e->getStatusCode(), 'message' => $e->getMessage()];
} catch (Throwable $e) {
    $result = ['status' => 'error', 'message' => $e::class.': '.$e->getMessage()];
}
$result['depth'] = DB::transactionLevel();
file_put_contents("$dir/$name.result", json_encode($result, JSON_THROW_ON_ERROR));
exit($result['status'] === 'error' ? 1 : 0);
