<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinancialProjectAssetTest extends TestCase
{
    use RefreshDatabase;

    private function seedAssetSetup(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#820ad1',
            'balance' => '10000.00',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Investimentos',
            'type' => 'expense',
            'system_key' => Category::SYSTEM_KEY_INVESTMENTS,
        ]);

        return compact('couple', 'user', 'account', 'category');
    }

    public function test_can_create_and_update_bitcoin_cofrinho(): void
    {
        ['user' => $user] = $this->seedAssetSetup();

        $response = $this->actingAs($user)->post(route('cofrinhos.store'), [
            'name' => 'Reserva em Bitcoin',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.05000000',
            'asset_avg_price' => '320000.00',
            'target_amount' => '50000.00',
            'color' => '#f59e0b',
        ]);

        $response->assertRedirect(route('cofrinhos.index'));
        $this->assertDatabaseHas('financial_projects', [
            'name' => 'Reserva em Bitcoin',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => 0.05,
            'asset_avg_price' => 320000.00,
        ]);

        $project = FinancialProject::where('name', 'Reserva em Bitcoin')->first();
        $this->assertTrue($project->isBitcoin());
        $this->assertTrue($project->isCustomAsset());
        $this->assertSame('BTC', $project->assetUnitLabel());
        $this->assertSame(16000.00, $project->totalInvestedBrl()); // 0.05 * 320000

        // Atualizar
        $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => 'Reserva Principal Bitcoin',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.06000000',
            'asset_avg_price' => '330000.00',
            'target_amount' => '60000.00',
            'color' => '#f59e0b',
        ])->assertRedirect(route('cofrinhos.index'));

        $project->refresh();
        $this->assertSame('Reserva Principal Bitcoin', $project->name);
        $this->assertSame(0.06, (float) $project->asset_quantity);
        $this->assertSame(330000.00, (float) $project->asset_avg_price);
        $this->assertSame(19800.00, $project->totalInvestedBrl()); // 0.06 * 330000
    }

    public function test_can_convert_existing_fiat_cofrinho_to_bitcoin(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedAssetSetup();

        // Cofrinho legado
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Cofrinho Antigo',
            'asset_type' => 'fiat',
            'target_amount' => '10000.00',
        ]);

        $this->assertFalse($project->isBitcoin());
        $this->assertFalse($project->isCustomAsset());

        // Converte para Bitcoin
        $this->actingAs($user)->put(route('cofrinhos.update', $project), [
            'name' => 'Cofrinho Convertido para BTC',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.02500000',
            'asset_avg_price' => '300000.00',
        ])->assertRedirect(route('cofrinhos.index'));

        $project->refresh();
        $this->assertTrue($project->isBitcoin());
        $this->assertSame(0.025, (float) $project->asset_quantity);
        $this->assertSame(300000.00, (float) $project->asset_avg_price);
    }

    public function test_recalculates_average_price_on_multiple_btc_aportes(): void
    {
        ['couple' => $couple] = $this->seedAssetSetup();

        // Posição inicial: 0.1 BTC a PM de R$ 300.000,00 (Total investido: R$ 30.000,00)
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'BTC Hodl',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.10000000',
            'asset_avg_price' => '300000.00',
        ]);

        // Aporte 1: R$ 10.000,00 comprando 0.02 BTC (a R$ 500.000,00/BTC)
        // Novo total: R$ 40.000,00 / 0.12 BTC = PM R$ 333.333,3333
        $res1 = $project->recalculateAveragePriceOnAporte(10000.00, 0.02, 500000.00);

        $this->assertEqualsWithDelta(0.12, $res1['new_quantity'], 0.000001);
        $this->assertEqualsWithDelta(333333.3333, $res1['new_avg_price'], 0.01);
        $this->assertEqualsWithDelta(40000.00, $res1['total_invested'], 0.01);

        // Aporte 2: R$ 20.000,00 comprando 0.08 BTC (a R$ 250.000,00/BTC na queda)
        // Novo total: R$ 60.000,00 / 0.20 BTC = PM R$ 300.000,00
        $res2 = $project->recalculateAveragePriceOnAporte(20000.00, 0.08, 250000.00);

        $this->assertEqualsWithDelta(0.20, $res2['new_quantity'], 0.000001);
        $this->assertEqualsWithDelta(300000.00, $res2['new_avg_price'], 0.01);
        $this->assertEqualsWithDelta(60000.00, $res2['total_invested'], 0.01);

        $project->refresh();
        $this->assertEqualsWithDelta(0.20, (float) $project->asset_quantity, 0.000001);
        $this->assertEqualsWithDelta(300000.00, (float) $project->asset_avg_price, 0.01);
    }

    public function test_store_asset_aporte_endpoint_with_and_without_account_transaction(): void
    {
        ['user' => $user, 'account' => $account, 'couple' => $couple] = $this->seedAssetSetup();

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Bitcoin Cofrinho',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.01000000',
            'asset_avg_price' => '300000.00',
        ]);

        $initialBalance = (float) $account->fresh()->balance;

        // 1. Aporte sem conta bancária
        $this->actingAs($user)->post(route('cofrinhos.asset-aporte.store', $project), [
            'amount' => '3000.00',
            'asset_quantity' => '0.00600000',
            'asset_unit_price' => '500000.00',
            'date' => '2026-08-27',
            'note' => 'Aporte direto',
        ])->assertRedirect(route('cofrinhos.index'));

        $this->assertDatabaseHas('financial_project_entries', [
            'financial_project_id' => $project->id,
            'type' => 'asset_aporte',
            'amount' => 3000.00,
            'asset_quantity' => 0.006,
        ]);

        $this->assertSame($initialBalance, (float) $account->fresh()->balance);

        // 2. Aporte vinculando conta bancária (deve debitar saldo e criar transação)
        $this->actingAs($user)->post(route('cofrinhos.asset-aporte.store', $project), [
            'amount' => '2000.00',
            'asset_quantity' => '0.00400000',
            'asset_unit_price' => '500000.00',
            'date' => '2026-08-27',
            'account_id' => $account->id,
            'note' => 'Aporte com débito bancário',
        ])->assertRedirect(route('cofrinhos.index'));

        $this->assertDatabaseHas('transactions', [
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'financial_project_id' => $project->id,
            'type' => 'expense',
            'amount' => 2000.00,
        ]);

        $this->assertEqualsWithDelta($initialBalance - 2000.00, (float) $account->fresh()->balance, 0.01);
    }

    public function test_get_quote_endpoint_returns_json(): void
    {
        ['user' => $user] = $this->seedAssetSetup();

        Http::fake([
            'https://api.binance.com/*' => Http::response([
                'lastPrice' => '550123.45',
                'priceChangePercent' => '2.5',
                'highPrice' => '560000.00',
                'lowPrice' => '540000.00',
            ], 200),
        ]);

        $response = $this->actingAs($user)->get(route('cofrinhos.quote', ['type' => 'crypto', 'code' => 'BTC', 'fresh' => 1]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'asset_type' => 'crypto',
                    'asset_code' => 'BTC',
                    'price' => 550123.45,
                ],
            ]);
    }

    public function test_movements_show_asset_columns_and_history(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedAssetSetup();

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Bitcoin Longo Prazo',
            'asset_type' => 'crypto',
            'asset_code' => 'BTC',
            'asset_quantity' => '0.05000000',
            'asset_avg_price' => '300000.00',
        ]);

        FinancialProjectEntry::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'financial_project_id' => $project->id,
            'type' => FinancialProjectEntry::TYPE_ASSET_APORTE,
            'amount' => '1500.00',
            'asset_quantity' => '0.00300000',
            'asset_unit_price' => '500000.00',
            'asset_resulting_avg_price' => '311320.75',
            'date' => '2026-08-26',
            'note' => 'Compra DCA mensal',
        ]);

        $this->actingAs($user)
            ->get(route('cofrinhos.movements', $project))
            ->assertOk()
            ->assertSee('Bitcoin')
            ->assertSee('Compra DCA mensal')
            ->assertSee('0,003')
            ->assertSee('R$ 500.000,00')
            ->assertSee('R$ 311.320,75');
    }
}
