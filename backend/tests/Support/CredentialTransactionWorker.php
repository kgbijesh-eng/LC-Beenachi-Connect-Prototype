<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialWorkerSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$path, $dir, $name] = array_slice($argv, 1);
$data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

$await = static function (string $file): void {
    $end = microtime(true) + 25;
    while (! is_file($file)) {
        if (microtime(true) > $end) {
            throw new RuntimeException('Credential worker barrier timed out.');
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
    $userLocked = false;
    DB::listen(function ($query) use ($data, $dir, $name, $await, &$held, &$userLocked): void {
        $club = str_contains($query->sql, '"clubs"') && str_contains($query->sql, 'for update');
        $user = str_contains($query->sql, 'pg_advisory_xact_lock');
        if ($club && $userLocked) {
            throw new RuntimeException('Club lock requested after the user lock.');
        }
        $userLocked = $userLocked || $user;
        $barrier = match ($data['hold'] ?? null) {
            'club' => $club,
            'user' => $user,
            'email' => str_starts_with($query->sql, 'update "users"'),
            default => false,
        };
        if (! $held && $barrier) {
            $held = true;
            file_put_contents("$dir/$name.locked", '1');
            $await("$dir/$name.finish");
        }
    });

    if (isset($data['suspend_membership'])) {
        $club = \App\Models\Club::where('code', $data['club_code'])->sole();
        $app->make(\App\Services\Access\OrganisationTransactionGuard::class)->run($club, function () use ($data, $club): void {
            \App\Models\ClubMembership::where('club_id', $club->id)->where('user_id', $data['suspend_membership'])
                ->lockForUpdate()->sole()->update(['status' => 'inactive']);
        });
        $status = 200;
    } elseif (isset($data['cli'])) {
        $command = $app->make(Kernel::class)->all()['account:activation-issue'];
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->setInputs(['yes']);
        $status = $tester->execute($data['cli']);
    } else {
        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if (isset($data['bearer'])) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$data['bearer'];
        }
        $request = Request::create($data['route'], 'POST', $data['payload'] ?? [], [], [], $headers);
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $status = $response->getStatusCode();
    }
    $result = ['status' => $status, 'depth' => DB::transactionLevel(), 'user_locked' => $userLocked];
} catch (Throwable $e) {
    // Never copy request data, SQL bindings or response bodies into evidence.
    $result = ['error' => $e::class, 'depth' => DB::transactionLevel()];
}
file_put_contents("$dir/$name.result", json_encode($result, JSON_THROW_ON_ERROR));
exit(isset($result['error']) ? 1 : 0);
