<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionEditCreditCardInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão Principal',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
            'credit_card_limit_total' => '5000.00',
        ]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado',
            'type' => 'expense',
            'color' => '#222',
        ]);

        return compact('couple', 'user', 'card', 'checking', 'category');
    }

    public function test_permite_alterar_fatura_de_compra_no_cartao_nao_parcelada_para_outra_fatura_aberta(): void
    {
        extract($this->seedData());

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compras do Mês',
            'amount' => '150.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [
            ['category_id' => $category->id, 'amount' => '150.00'],
        ]);

        // Fatura de origem foi materializada com 150.00
        $stmtOrigin = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmtOrigin);
        $this->assertSame('150.00', number_format((float) $stmtOrigin->spent_total, 2, '.', ''));

        // Altera a fatura para maio/2026 (05/2026)
        $response = $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Compras do Mês',
            'amount' => '150.00',
            'date' => '2026-04-10',
            'reference_month' => 5,
            'reference_year' => 2026,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Fatura do lançamento atualizada.');

        $tx->refresh();
        $this->assertSame(5, (int) $tx->reference_month);
        $this->assertSame(2026, (int) $tx->reference_year);

        // Fatura antiga atualizada para 0.00
        $stmtOrigin->refresh();
        $this->assertSame('0.00', number_format((float) $stmtOrigin->spent_total, 2, '.', ''));

        // Nova fatura materializada com 150.00
        $stmtDest = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmtDest);
        $this->assertSame('150.00', number_format((float) $stmtDest->spent_total, 2, '.', ''));
    }

    public function test_bloqueia_alteracao_de_fatura_se_fatura_de_destino_possui_pagamentos_ou_esta_quitada(): void
    {
        extract($this->seedData());

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Jantar',
            'amount' => '100.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [
            ['category_id' => $category->id, 'amount' => '100.00'],
        ]);

        // Cria fatura de maio/2026 e vincula um pagamento a ela
        $stmtMay = CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 5,
            'reference_year' => 2026,
            'spent_total' => '200.00',
            'due_date' => '2026-05-20',
            'paid_at' => '2026-05-18',
        ]);

        $paymentTx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura maio',
            'amount' => '200.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-18',
            'reference_month' => 5,
            'reference_year' => 2026,
        ]);
        $stmtMay->paymentTransactions()->attach($paymentTx->id);

        // Tenta mover a compra para maio (bloqueada)
        $response = $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Jantar',
            'amount' => '100.00',
            'date' => '2026-04-10',
            'reference_month' => 5,
            'reference_year' => 2026,
        ]);

        $response->assertSessionHasErrors('reference_month');

        $tx->refresh();
        $this->assertSame(4, (int) $tx->reference_month);
    }

    public function test_bloqueia_alteracao_de_fatura_se_fatura_de_destino_e_avulsa(): void
    {
        extract($this->seedData());

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Farmácia',
            'amount' => '80.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [
            ['category_id' => $category->id, 'amount' => '80.00'],
        ]);

        // Fatura avulsa em junho/2026
        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 6,
            'reference_year' => 2026,
            'spent_total' => '500.00',
            'due_date' => '2026-06-20',
            'is_avulsa' => true,
        ]);

        $response = $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Farmácia',
            'amount' => '80.00',
            'date' => '2026-04-10',
            'reference_month' => 6,
            'reference_year' => 2026,
        ]);

        $response->assertSessionHasErrors('reference_month');

        $tx->refresh();
        $this->assertSame(4, (int) $tx->reference_month);
    }

    public function test_bloqueia_alteracao_de_fatura_para_compra_parcelada(): void
    {
        extract($this->seedData());

        $parent = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Geladeira (Parcela 1/2)',
            'amount' => '500.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '500.00']]);

        $child = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Geladeira (Parcela 2/2)',
            'amount' => '500.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ], [['category_id' => $category->id, 'amount' => '500.00']]);

        // Tenta alterar a fatura da primeira parcela
        $response = $this->actingAs($user)->put(route('transactions.update', $parent), [
            'description' => 'Geladeira',
            'amount' => '500.00',
            'date' => '2026-04-10',
            'reference_month' => 6,
            'reference_year' => 2026,
        ]);

        $response->assertSessionHasErrors('reference_month');

        $parent->refresh();
        $this->assertSame(4, (int) $parent->reference_month);
    }

    public function test_open_invoice_cycles_for_account_retorna_apenas_faturas_abertas(): void
    {
        extract($this->seedData());

        // 1. Fatura paga
        $stmtPaid = CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 1,
            'reference_year' => 2026,
            'spent_total' => '100.00',
            'paid_at' => '2026-01-20',
        ]);
        $payTx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura jan',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-01-20',
            'reference_month' => 1,
            'reference_year' => 2026,
        ]);
        $stmtPaid->paymentTransactions()->attach($payTx->id);

        // 2. Fatura avulsa
        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 2,
            'reference_year' => 2026,
            'spent_total' => '200.00',
            'is_avulsa' => true,
        ]);

        // 3. Fatura com despesa aberta
        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Livro',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-03-10',
            'reference_month' => 3,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $openCycles = CreditCardStatement::openInvoiceCyclesForAccount($card);

        $cycleKeys = array_map(fn ($c) => $c['month'].'-'.$c['year'], $openCycles);

        // 01/2026 (paga) e 02/2026 (avulsa) NÃO podem estar na lista
        $this->assertNotContains('1-2026', $cycleKeys);
        $this->assertNotContains('2-2026', $cycleKeys);

        // Meses passados sem histórico (ex: 12/2025) NÃO devem ser inventados
        $this->assertNotContains('12-2025', $cycleKeys);

        // 03/2026 (aberta com despesa) e meses atuais/futuros abertos DEVEM estar na lista
        $this->assertContains('3-2026', $cycleKeys);
    }
}
