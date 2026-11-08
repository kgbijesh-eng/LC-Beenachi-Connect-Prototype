<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $payloadPath, $barrier] = $argv;
$p = json_decode(file_get_contents($payloadPath), true, flags: JSON_THROW_ON_ERROR);
try {
    // Must pass independently before this worker writes even synthetic users.
    $safe = DB::selectOne("SELECT current_database()='lcb_p02b2_cutover_test' AND current_user='lc_beenachi_app' AND current_user=pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT EXISTS(SELECT 1 FROM pg_auth_members WHERE member=r.oid) AND NOT EXISTS(SELECT 1 FROM pg_database WHERE datname NOT IN ('lcb_p02b2_cutover_test','postgres','template0','template1')) AS safe FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()");
    if (! in_array($safe->safe, [true, 1, '1', 't'], true)) { throw new RuntimeException('Unsafe outcome worker database.'); }
    DB::transaction(function () use ($p, $barrier): void {
        if ($p['mode'] === 'serialization') {
            DB::table('users')->where('id', $p['a'])->update(['name' => 'Concurrent committed update']);
            return;
        }
        if (! in_array($p['mode'], ['deadlock-b', 'deadlock-c'], true)) { throw new RuntimeException('Unknown worker mode.'); }
        $first = $p['mode'] === 'deadlock-b';
        DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$first ? $p['b'] : $p['c']]);
        file_put_contents($barrier.'/pid', (string) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid);
        file_put_contents($barrier.'/ready', '1');
        $deadline = microtime(true) + 20;
        while (! $first && ! is_file($p['peer_pid_path']) && microtime(true) < $deadline) { usleep(5_000); }
        $waiter = $first ? $p['pid'] : (int) file_get_contents($p['peer_pid_path']);
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND pg_backend_pid()=ANY(pg_blocking_pids(pid))", [$waiter])->n;
            if ((int) $waiting > 0) { break; }
            usleep(5_000);
        } while (microtime(true) < $deadline);
        if ((int) $waiting === 0) { throw new RuntimeException('Required PostgreSQL blocking edge was not observed.'); }
        file_put_contents($barrier.'/edge.json', json_encode(['waiter' => $waiter, 'blocker' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid], JSON_THROW_ON_ERROR));
        // Stage A->B, B->C, C->A. Both real blocking edges are observed;
        // the small offset separates detector deadlines without changing settings.
        usleep(200_000);
        DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$first ? $p['c'] : $p['a']]);
    }, 1);
    file_put_contents($barrier.'/result.json', json_encode(['outcome' => 'committed'], JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    file_put_contents($barrier.'/result.json', json_encode(['outcome' => 'error', 'class' => $e::class, 'code' => (string) $e->getCode(), 'message' => $e->getMessage()], JSON_THROW_ON_ERROR));
    exit(1);
}
