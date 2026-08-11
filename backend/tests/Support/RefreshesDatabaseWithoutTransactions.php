<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;

trait RefreshesDatabaseWithoutTransactions
{
    use CanConfigureMigrationCommands;

    protected function setUpRefreshesDatabaseWithoutTransactions(): void
    {
        $this->migrateFreshWithoutTransaction();
    }

    protected function tearDownRefreshesDatabaseWithoutTransactions(): void
    {
        $this->migrateFreshWithoutTransaction();
    }

    private function migrateFreshWithoutTransaction(): void
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
        $this->app[Kernel::class]->setArtisan(null);
        RefreshDatabaseState::$migrated = true;
    }
}
