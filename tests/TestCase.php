<?php

namespace Tests;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $this->forceInMemorySqliteForTestProcess();
        $this->assertDatabaseTestingTraitsUseOnlyInMemorySqlite();

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @param  array<int, array{category_id: int, amount: string}>  $splits
     */
    protected function createTransactionWithSplits(array $attributes, array $splits): Transaction
    {
        unset($attributes['category_id']);
        $t = Transaction::create($attributes);
        $t->syncCategorySplits($splits);

        return $t->fresh();
    }

    private function assertDatabaseTestingTraitsUseOnlyInMemorySqlite(): void
    {
        $uses = array_flip(class_uses_recursive(static::class));
        $usesDatabaseTestingTrait = isset($uses[RefreshDatabase::class])
            || isset($uses[DatabaseMigrations::class])
            || isset($uses[DatabaseTruncation::class])
            || isset($uses[DatabaseTransactions::class]);

        if (! $usesDatabaseTestingTrait) {
            return;
        }

        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) === 'sqlite' && ($config['database'] ?? null) === ':memory:') {
            return;
        }

        throw new RuntimeException(
            'Testes com traits de banco so podem rodar em SQLite :memory:. Verifique phpunit.xml/config cache antes de executar a suite.'
        );
    }

    private function forceInMemorySqliteForTestProcess(): void
    {
        $previousConnection = (string) config('database.default');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.foreign_key_constraints', true);

        if ($previousConnection !== 'sqlite') {
            DB::purge($previousConnection);
        }

        DB::setDefaultConnection('sqlite');
    }
}
