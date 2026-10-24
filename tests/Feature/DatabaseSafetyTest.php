<?php

namespace Tests\Feature;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_provides_the_schema_without_migration_tracking(): void
    {
        $this->assertTrue(Schema::hasTable('transactions'));
        $this->assertFalse(Schema::hasTable('migrations'));
    }

    #[DataProvider('blockedCommands')]
    public function test_console_guard_rejects_schema_commands_before_execution(string $command): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('proibidas neste projeto');
        // Dispatch the pre-execution event only. No command or migration is run.
        Event::dispatch(new CommandStarting($command, new ArrayInput([]), new BufferedOutput));
    }

    public static function blockedCommands(): array
    {
        return array_map(fn ($command) => [$command], [
            'migrate', 'migrate:fresh', 'migrate:rollback', 'migrate:reset', 'db:wipe',
        ]);
    }
}
