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

class CreditCardStatementTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoupleWithAccounts(): array
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
            'name' => 'Conta corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        return compact('couple', 'user', 'card', 'checking', 'category');
    }

    private function cardExpense(User $user, Account $card, Category $category, int $month, int $year, string $amount = '100.00'): Transaction
    {
        return Transaction::create([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra cartão',
            'amount' => $amount,
            'payment_method' => null,
            'type' => 'expense',
            'date' => sprintf('%04d-%02d-10', $year, $month),
            'reference_month' => $month,
            'reference_year' => $year,
        ]);
    }

    public function test_index_lista_fatura_a_partir_de_lancamento_no_cartao(): void
    {
        extract($this->seedCoupleWithAccounts());

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertSee('Ainda não há despesas em cartão com mês de referência', false);

        $this->cardExpense($user, $card, $category, 4, 2026, '150.50');

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertSee('R$ 150,50', false)
            ->assertSee('04/2026', false);
    }

    public function test_materialize_atualiza_vencimento_legado_do_mes_seguinte(): void
    {
        extract($this->seedCoupleWithAccounts());
        $card->update(['credit_card_invoice_due_day' => 10]);

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 6,
            'reference_year' => 2026,
            'spent_total' => '0.00',
            'due_date' => '2026-07-10',
            'paid_at' => null,
            'payment_transaction_id' => null,
        ]);

        $this->cardExpense($user, $card, $category, 6, 2026, '10.00');

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 6)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertSame('2026-06-10', $meta->due_date->toDateString());
    }

    public function test_materialize_nao_sobrescreve_vencimento_personalizado(): void
    {
        extract($this->seedCoupleWithAccounts());
        $card->update(['credit_card_invoice_due_day' => 10]);

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 6,
            'reference_year' => 2026,
            'spent_total' => '0.00',
            'due_date' => '2026-06-25',
            'paid_at' => null,
            'payment_transaction_id' => null,
        ]);

        $this->cardExpense($user, $card, $category, 6, 2026, '5.00');

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 6)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertSame('2026-06-25', $meta->due_date->toDateString());
    }

    public function test_primeiro_lancamento_cartao_materializa_fatura_com_vencimento_previsto(): void
    {
        extract($this->seedCoupleWithAccounts());
        $card->update(['credit_card_invoice_due_day' => 10]);
        $this->cardExpense($user, $card, $category, 4, 2026, '40.00');

        $this->assertDatabaseHas('credit_card_statements', [
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertSame('2026-04-10', $meta->due_date->toDateString());
        $this->assertEquals(40.0, (float) $meta->spent_total);

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertSee('10/04/2026', false)
            ->assertSee('data-edit-due="2026-04-10"', false)
            ->assertDontSee('Sug. 10/04/2026', false);
    }

    public function test_segundo_lancamento_no_mesmo_ciclo_atualiza_spent_total_materializado(): void
    {
        extract($this->seedCoupleWithAccounts());
        $card->update(['credit_card_invoice_due_day' => 10]);
        $this->cardExpense($user, $card, $category, 4, 2026, '40.00');
        $this->cardExpense($user, $card, $category, 4, 2026, '35.50');

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertEquals(75.5, (float) $meta->spent_total);
    }

    public function test_primeiro_attach_payment_grava_vencimento_padrao_do_cartao(): void
    {
        extract($this->seedCoupleWithAccounts());
        $card->update(['credit_card_invoice_due_day' => 10]);
        $this->cardExpense($user, $card, $category, 4, 2026, '25.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 4]), [
            'mode' => 'create',
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'category_id' => $category->id,
            'paid_date' => '2026-05-12',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertSame('2026-04-10', $meta->due_date->toDateString());
        $this->assertTrue($meta->isPaid());
    }

    public function test_editar_vencimento_e_data_pagamento_cria_ou_atualiza_metadados(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 4, 2026);

        $this->actingAs($user)->put(route('credit-card-statements.update', [$card, 2026, 4]), [
            'due_date' => '2026-05-10',
            'paid_at' => '2026-05-12',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertNotNull($meta);
        $this->assertSame('2026-05-10', $meta->due_date->toDateString());
        $this->assertTrue($meta->isPaid());
        $this->assertNull($meta->payment_transaction_id);
    }

    public function test_gerar_lancamento_na_conta_marca_fatura_paga(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 2, 2026, '300.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 2]), [
            'mode' => 'create',
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'category_id' => $category->id,
            'paid_date' => '2026-03-08',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertNotNull($meta);
        $this->assertNotNull($meta->payment_transaction_id);
        $this->assertTrue($meta->isPaid());

        $tx = Transaction::find($meta->payment_transaction_id);
        $this->assertNotNull($tx);
        $this->assertSame($checking->id, $tx->account_id);
        $this->assertEquals(300.0, (float) $tx->amount);
        $this->assertStringContainsString('Pagamento fatura', $tx->description);
    }

    public function test_vincular_lancamento_existente(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 1, 2026, '50.00');

        $tx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento manual',
            'amount' => '50.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-02-04',
            'reference_month' => 2,
            'reference_year' => 2026,
        ]);

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 1]), [
            'mode' => 'link',
            'existing_transaction_id' => $tx->id,
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertSame($tx->id, $meta->payment_transaction_id);
        $this->assertSame('2026-02-04', $meta->paid_at->toDateString());
    }

    public function test_desvincular_remove_pagamento(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 5, 2026, '10.00');

        $tx = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $checking->id,
            'description' => 'X',
            'amount' => '10.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-06-01',
            'reference_month' => 6,
            'reference_year' => 2026,
        ]);

        CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->update([
                'paid_at' => '2026-06-01',
                'payment_transaction_id' => $tx->id,
            ]);

        $this->actingAs($user)->post(route('credit-card-statements.detach-payment', [$card, 2026, 5]))
            ->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertNull($meta->payment_transaction_id);
        $this->assertNull($meta->paid_at);
    }

    public function test_nao_segundo_vinculo_se_ja_existe_pagamento(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 7, 2026, '10.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 7]), [
            'mode' => 'create',
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'category_id' => $category->id,
            'paid_date' => '2026-08-02',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 7]), [
            'mode' => 'create',
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'category_id' => $category->id,
            'paid_date' => '2026-08-03',
        ])->assertSessionHasErrors('mode');
    }

    public function test_outro_casal_nao_atualiza_fatura(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 8, 2026);

        $other = Couple::factory()->create();
        $intruder = User::factory()->create(['couple_id' => $other->id]);

        $this->actingAs($intruder)->put(route('credit-card-statements.update', [$card, 2026, 8]), [
            'due_date' => '2026-09-02',
        ])->assertForbidden();
    }

    public function test_update_retorna_404_se_nao_ha_despesa_no_ciclo(): void
    {
        extract($this->seedCoupleWithAccounts());

        $this->actingAs($user)->put(route('credit-card-statements.update', [$card, 2026, 11]), [
            'due_date' => '2026-12-01',
        ])->assertNotFound();
    }
}
