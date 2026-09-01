<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCreditCardListingPurchaseDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_despesa_cartao_listada_pelo_mes_da_data_da_compra_nao_pela_referencia(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Loja',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $parent = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'TV (Parcela 1/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'TV (Parcela 2/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 6,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $apr = $this->actingAs($user)->get(route('dashboard', ['period' => '2026-04']));
        $apr->assertOk();
        $apr->assertSee('TV', false);
        $apr->assertSee('100,00', false);
        $apr->assertSee('em 2x', false);

        $may = $this->actingAs($user)->get(route('dashboard', ['period' => '2026-05']));
        $may->assertOk();
        $may->assertDontSee('TV', false);
    }

    public function test_focus_transaction_com_id_de_parcela_filha_mostra_apenas_a_raiz_visivel(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Loja',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $parent = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'TV (Parcela 1/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $child = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'TV (Parcela 2/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 6,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => '2026-04',
            'account_id' => $card->id,
            'focus_transaction' => $child->id,
        ]));

        $response->assertOk();
        $response->assertSee('A mostrar apenas o lançamento aberto a partir da fatura', false);
        $response->assertSee('id="dashboard-tx-'.$parent->id.'"', false);
    }

    public function test_lista_de_lancamentos_e_painel_exibem_fatura_para_compra_no_cartao(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank Black',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#820ad1',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado',
            'type' => 'expense',
            'color' => '#10b981',
        ]);

        // Compra à vista no cartão de crédito em 2026-05-15 com fatura em 2026-06
        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compras do Mês',
            'amount' => '250.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-05-15',
            'reference_month' => 6,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '250.00']]);

        // 1. Dashboard (Painel Financeiro)
        $dash = $this->actingAs($user)->get(route('dashboard', ['period' => '2026-05']));
        $dash->assertOk();
        $dash->assertSee('Nubank Black', false);
        $dash->assertSee('Fatura 06/2026', false);
        $dash->assertSee('#statement-cycle-'.$card->id.'-2026-6', false);
        $dash->assertSee('target="_blank"', false);

        // 2. Lançamentos (Histórico Completo)
        $txIndex = $this->actingAs($user)->get(route('transactions.index', ['period' => '2026-05']));
        $txIndex->assertOk();
        $txIndex->assertSee('Nubank Black', false);
        $txIndex->assertSee('Fatura 06/2026', false);
        $txIndex->assertSee('#statement-cycle-'.$card->id.'-2026-6', false);
        $txIndex->assertSee('target="_blank"', false);
    }
}

