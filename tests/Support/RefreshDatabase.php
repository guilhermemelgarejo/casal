<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait RefreshDatabase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function refreshTestDatabase()
    {
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('A fixture de testes só pode ser carregada em SQLite :memory:.');
        }

        if (! RefreshDatabaseState::$migrated) {
            DB::connection()->getPdo()->exec(file_get_contents(__DIR__.'/schema.sqlite.sql'));
            RefreshDatabaseState::$inMemoryConnections[config('database.default')] = DB::connection()->getPdo();
            RefreshDatabaseState::$inMemoryConnections[null] = DB::connection()->getPdo();
            RefreshDatabaseState::$migrated = true;
        }
        $this->beginDatabaseTransaction();
    }
}
