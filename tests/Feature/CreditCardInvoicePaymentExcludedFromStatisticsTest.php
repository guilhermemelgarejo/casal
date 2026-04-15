<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardInvoicePaymentExcludedFromStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagamento_de_fatura_entra_nos_kpis_do_dashboard_e_lista_sem_soma_errada(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
        ]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'CC',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $catExpense = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Compras',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $refMonth = 6;
        $refYear = 2026;

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Supermercado',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => Carbon::createFromDate($refYear, $refMonth, 5)->toDateString(),
            'reference_month' => $refMonth,
            'reference_year' => $refYear,
        ], [['category_id' => $catExpense->id, 'amount' => '100.00']]);

        $stmt = CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 5,
            'reference_year' => $refYear,
            'due_date' => Carbon::createFromDate($refYear, $refMonth, 10)->toDateString(),
            'paid_at' => null,
        ]);

        $paymentTx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura Visa (05/'.$refYear.')',
            'amount' => '500.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => Carbon::createFromDate($refYear, $refMonth, 8)->toDateString(),
            'reference_month' => $refMonth,
            'reference_year' => $refYear,
        ], [['category_id' => $catExpense->id, 'amount' => '500.00']]);

        $stmt->paymentTransactions()->attach($paymentTx->id);

        $period = sprintf('%04d-%02d', $refYear, $refMonth);

        $dash = $this->actingAs($user)->get(route('dashboard', ['period' => $period]));
        $dash->assertOk();
        $dash->assertSee('R$ 100,00', false);
        $dash->assertSee('R$ 600,00', false);
        $dash->assertSee('Pagamento fatura Visa', false);
        $dash->assertSee('R$ 500,00', false);

        $txPage = $this->actingAs($user)->get(route('dashboard', ['period' => $period]));
        $txPage->assertOk();
        $txPage->assertSee('R$ 100,00', false);
        $txPage->assertSee('R$ 600,00', false);
    }

    public function test_pagamento_de_fatura_nao_entra_no_gasto_por_categoria_do_orcamento(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
        ]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'CC',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Geral',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $m = (int) date('m');
        $y = (int) date('Y');

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Item',
            'amount' => '40.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => $m,
            'reference_year' => $y,
        ], [['category_id' => $cat->id, 'amount' => '40.00']]);

        $stmt = CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => $m === 1 ? 12 : $m - 1,
            'reference_year' => $m === 1 ? $y - 1 : $y,
            'due_date' => now()->toDateString(),
            'paid_at' => null,
        ]);

        $pay = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura',
            'amount' => '200.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => now()->toDateString(),
            'reference_month' => $m,
            'reference_year' => $y,
        ], [['category_id' => $cat->id, 'amount' => '200.00']]);
        $stmt->paymentTransactions()->attach($pay->id);

        Budget::create([
            'couple_id' => $couple->id,
            'category_id' => $cat->id,
            'amount' => '300.00',
            'month' => date('m'),
            'year' => date('Y'),
        ]);

        $page = $this->actingAs($user)->followingRedirects()->get(route('budgets.index'));
        $page->assertOk();
        $page->assertSee('40,00', false);
        $page->assertDontSee('240,00', false);
        $page->assertSee('300,00', false);
    }
}
