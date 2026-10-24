<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_deploy_token_returns_error(): void
    {
        config(['app.deploy_token' => null]);
        $this->postJson('/system/deploy-hook')->assertStatus(500)->assertJson(['status' => 'error']);
    }

    public function test_invalid_deploy_token_is_rejected(): void
    {
        config(['app.deploy_token' => 'valid-token']);
        $this->postJson('/system/deploy-hook', ['token' => 'invalid-token'])->assertForbidden();
    }

    public function test_success_only_runs_cache_commands_and_never_migrations(): void
    {
        config(['app.deploy_token' => 'valid-token']);
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command)->andReturn(0);
        }
        $this->postJson('/system/deploy-hook', [], ['X-Deploy-Token' => 'valid-token'])
            ->assertOk()->assertJson(['status' => 'success']);
    }

    public function test_command_failure_stops_deploy_and_returns_http_error(): void
    {
        config(['app.deploy_token' => 'valid-token']);
        Artisan::shouldReceive('call')->once()->with('config:clear')->andReturn(1);
        $this->postJson('/system/deploy-hook', [], ['X-Deploy-Token' => 'valid-token'])
            ->assertStatus(500)->assertJson(['status' => 'error']);
    }

    public function test_exception_returns_http_error_and_does_not_leak_details(): void
    {
        config(['app.deploy_token' => 'valid-token']);
        Artisan::shouldReceive('call')->once()->with('config:clear')->andThrow(new \RuntimeException('private detail'));
        $this->postJson('/system/deploy-hook', [], ['X-Deploy-Token' => 'valid-token'])
            ->assertStatus(500)->assertDontSee('private detail');
    }

    public function test_get_cannot_trigger_a_deploy(): void
    {
        $this->getJson('/system/deploy-hook')->assertStatus(405);
    }

    public function test_deploy_hook_preserves_financial_records_and_database_cache(): void
    {
        config(['app.deploy_token' => 'valid-token']);
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $account = Account::create(['couple_id' => $couple->id, 'name' => 'Conta teste', 'kind' => 'regular']);
        $category = Category::create(['couple_id' => $couple->id, 'name' => 'Receita teste', 'type' => 'income']);
        $transaction = Transaction::create([
            'couple_id' => $couple->id, 'user_id' => $user->id, 'account_id' => $account->id,
            'description' => 'Receita preservada', 'amount' => '123.45', 'type' => 'income',
            'date' => '2026-10-23', 'reference_year' => 2026, 'reference_month' => 10,
        ]);
        $transaction->syncCategorySplits([['category_id' => $category->id, 'amount' => '123.45']]);
        Cache::store('database')->put('deploy-preservation', 'preservar', 3600);

        $tables = ['users', 'couples', 'accounts', 'categories', 'transactions', 'transaction_category_splits', 'cache'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::shouldReceive('call')->once()->with($command)->andReturn(0);
        }
        $this->postJson('/system/deploy-hook', [], ['X-Deploy-Token' => 'valid-token'])->assertOk();

        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson(), $table.' foi alterada');
        }
        $this->assertSame('preservar', Cache::store('database')->get('deploy-preservation'));
    }
}
