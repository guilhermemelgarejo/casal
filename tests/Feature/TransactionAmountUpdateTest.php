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

class TransactionAmountUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualiza_valor_em_conta_regular_e_reescala_categorias(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $c1 = Category::create([
            'couple_id' => $couple->id,
            'name' => 'A',
            'type' => 'expense',
            'color' => '#222',
        ]);
        $c2 = Category::create([
            'couple_id' => $couple->id,
            'name' => 'B',
            'type' => 'expense',
            'color' => '#333',
        ]);

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [
            ['category_id' => $c1->id, 'amount' => '30.00'],
            ['category_id' => $c2->id, 'amount' => '70.00'],
        ]);

        $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Compra',
            'amount' => '200.00',
        ])->assertSessionHasNoErrors();

        $tx->refresh();
        $this->assertSame('200.00', number_format((float) $tx->amount, 2, '.', ''));
        $splits = $tx->categorySplits()->orderBy('id')->get();
        $this->assertCount(2, $splits);
        $this->assertSame('60.00', number_format((float) $splits[0]->amount, 2, '.', ''));
        $this->assertSame('140.00', number_format((float) $splits[1]->amount, 2, '.', ''));
    }

    public function test_bloqueia_edicao_de_pagamento_de_fatura(): void
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
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $invoiceCategory = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#333',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '100.00']]);

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 4]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-05-10',
        ])->assertSessionHasNoErrors();

        $paymentTx = Transaction::query()
            ->whereHas('creditCardStatementsPaidFor')
            ->firstOrFail();

        $this->actingAs($user)->put(route('transactions.update', $paymentTx), [
            'amount' => '50.00',
        ])->assertSessionHas('error');

        $paymentTx->refresh();
        $this->assertNotSame('50.00', $paymentTx->amount);
    }

    public function test_bloqueia_edicao_no_cartao_se_fatura_tem_pagamento_parcial(): void
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
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#333',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $purchase = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '100.00']]);

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 4]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-05-10',
            'amount' => '20.00',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertTrue($meta->hasPartialPayments());

        $this->actingAs($user)->put(route('transactions.update', $purchase), [
            'description' => 'Compra',
            'amount' => '80.00',
        ])->assertSessionHas('error');

        $purchase->refresh();
        $this->assertSame('100.00', number_format((float) $purchase->amount, 2, '.', ''));
    }

    public function test_permite_edicao_no_cartao_sem_pagamentos_e_atualiza_spent_total(): void
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
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $purchase = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '100.00']]);

        $this->actingAs($user)->put(route('transactions.update', $purchase), [
            'description' => 'Compra',
            'amount' => '45.50',
        ])->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame('45.50', number_format((float) $purchase->amount, 2, '.', ''));

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertEquals(45.5, (float) $meta->spent_total);
    }

    public function test_atualiza_apenas_descricao_sem_alterar_valor_nem_splits(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $c1 = Category::create([
            'couple_id' => $couple->id,
            'name' => 'A',
            'type' => 'expense',
            'color' => '#222',
        ]);
        $c2 = Category::create([
            'couple_id' => $couple->id,
            'name' => 'B',
            'type' => 'expense',
            'color' => '#333',
        ]);

        $tx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Antiga',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [
            ['category_id' => $c1->id, 'amount' => '30.00'],
            ['category_id' => $c2->id, 'amount' => '70.00'],
        ]);

        $this->actingAs($user)->put(route('transactions.update', $tx), [
            'description' => 'Nova descrição',
            'amount' => '100.00',
        ])->assertSessionHasNoErrors();

        $tx->refresh();
        $this->assertSame('Nova descrição', $tx->description);
        $this->assertSame('100.00', number_format((float) $tx->amount, 2, '.', ''));
        $splits = $tx->categorySplits()->orderBy('id')->get();
        $this->assertSame('30.00', number_format((float) $splits[0]->amount, 2, '.', ''));
        $this->assertSame('70.00', number_format((float) $splits[1]->amount, 2, '.', ''));
    }

    public function test_ao_editar_descricao_em_parcela_mantem_sufixo_parcela(): void
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
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $parcel = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'TV (Parcela 1/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '50.00']]);

        $this->actingAs($user)->put(route('transactions.update', $parcel), [
            'description' => 'Smart TV',
            'amount' => '50.00',
        ])->assertSessionHasNoErrors();

        $parcel->refresh();
        $this->assertSame('Smart TV (Parcela 1/2)', $parcel->description);
    }
}
