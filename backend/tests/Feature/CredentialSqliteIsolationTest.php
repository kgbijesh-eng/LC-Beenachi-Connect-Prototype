<?php

namespace Tests\Feature;

use Tests\TestCase;

class CredentialSqliteIsolationTest extends TestCase
{
    public function test_backend_tests_are_forced_to_isolated_in_memory_sqlite(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
