<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\Reports\ReportCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3gnReportsClosureReviewTest extends TestCase
{
    use RefreshDatabase;

    private const CHAIN = [
        'cff5b5e573bd6bf16b91f169d4c46a53befed3bd' => 'Add reports scope gate and provenance review',
        'dd08c3539bfa1a5dd8383d2ee92c9dec3f002f1e' => 'Approve reports authorization and API contract',
        'b9c896888156b997f2e7e95e6c599e67af7c7829' => 'Add reports catalog and query foundation',
        '599c97c9a256f9925a8f8b3772f44294e9d05735' => 'Add verified reports backend',
        '153d923a0310abb8ebb71dc223feccf1ce753781' => 'Connect reports frontend',
        '95f223bbce6bd727e688512ade2e483f912b59ff' => 'Verify reports browser workflows',
    ];

    public function test_reports_architecture_authorization_and_deferred_boundaries_are_closed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $catalog = app(ReportCatalog::class)->definitions();
        $this->assertSame(
            ['membership-summary', 'events-register', 'projects-register', 'reminders-register'],
            array_column($catalog, 'key')
        );
        $this->assertNotContains('collections-summary', array_column($catalog, 'key'));

        foreach (['president-admin', 'secretary', 'treasurer'] as $slug) {
            $this->assertTrue(Role::where('slug', $slug)->firstOrFail()->permissions()->where('slug', 'reports.view')->exists());
        }
        $this->assertFalse(Role::where('slug', 'member')->firstOrFail()->permissions()->where('slug', 'reports.view')->exists());

        $reportRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'clubs/{club}/reports'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->sort()->values()->all();
        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/reports',
            'GET|HEAD api/v1/clubs/{club}/reports/{reportKey}',
        ], $reportRoutes);

        $this->assertFalse(Schema::hasTable('reports'));
        $this->assertFalse(Schema::hasTable('generated_reports'));

        $doc = file_get_contents(__DIR__.'/../../docs/milestone_3gn_reports_closure_review.md');
        foreach ([
            'fixed backend catalog', 'route-derived club scope', 'backend summaries',
            'backend pagination', 'no report persistence table', 'Unknown keys return 404',
            'invalid filters return 422', 'inclusive in the club timezone', 'converted to UTC',
            'duplicate-title stable IDs', 'cross-club exclusion', 'privacy-minimized',
            'no document-level horizontal overflow', 'genuine PostgreSQL database',
            'collections-summary` remains deferred', 'PASS.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $doc);
        }
    }

    public function test_commit_chain_and_closure_scope_are_lifecycle_safe(): void
    {
        $root = dirname(__DIR__, 3);
        $log = $this->git($root, ['log', '--all', '--format=%H%x09%s']);

        foreach (self::CHAIN as $hash => $subject) {
            $this->assertStringContainsString("{$hash}\t{$subject}", $log);
        }

        $hashes = array_keys(self::CHAIN);
        for ($i = 0; $i < count($hashes) - 1; $i++) {
            $process = new Process(['git', 'merge-base', '--is-ancestor', $hashes[$i], $hashes[$i + 1]], $root);
            $process->run();
            $this->assertTrue($process->isSuccessful(), "{$hashes[$i]} is not an ancestor of {$hashes[$i + 1]}");
        }

        $closure = collect(preg_split('/\R/', trim($log)))
            ->first(fn (string $line) => str_ends_with($line, "\tClose reports backend activation"));
        $expected = [
            'backend/docs/milestone_3gn_reports_closure_review.md',
            'backend/tests/Feature/Milestone3gnReportsClosureReviewTest.php',
        ];

        if ($closure) {
            [$hash] = explode("\t", $closure, 2);
            $files = preg_split('/\R/', trim($this->git($root, ['diff-tree', '--no-commit-id', '--name-only', '-r', $hash])));
        } else {
            $status = preg_split('/\R/', trim($this->git($root, ['status', '--porcelain=v1'])));
            $files = array_map(fn (string $line) => str_replace('\\', '/', substr($line, 3)), $status);
        }

        sort($expected);
        sort($files);
        $this->assertSame($expected, $files);
    }

    private function git(string $root, array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $root);
        $process->setTimeout(30);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        return str_replace("\r\n", "\n", $process->getOutput());
    }
}
