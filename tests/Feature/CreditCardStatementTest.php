<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
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

        $invoiceCategory = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#333',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        return compact('couple', 'user', 'card', 'checking', 'category', 'invoiceCategory');
    }

    private function cardExpense(User $user, Account $card, Category $category, int $month, int $year, string $amount = '100.00'): Transaction
    {
        return $this->createTransactionWithSplits([
            'couple_id' => $user->couple_id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra cartão',
            'amount' => $amount,
            'payment_method' => null,
            'type' => 'expense',
            'date' => sprintf('%04d-%02d-10', $year, $month),
            'reference_month' => $month,
            'reference_year' => $year,
        ], [['category_id' => $category->id, 'amount' => $amount]]);
    }

    public function test_index_lista_fatura_a_partir_de_lancamento_no_cartao(): void
    {
        extract($this->seedCoupleWithAccounts());

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertSee('Escolher cartão', false)
            ->assertSee('cc-picker-grid', false);

        $this->cardExpense($user, $card, $category, 4, 2026, '150.50');

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertSee('R$ 150,50', false)
            ->assertDontSee('id="statement-cycle-', false)
            ->assertDontSee('Itens da fatura', false);

        $html = $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => $card->id]))
            ->assertOk()
            ->assertSee('R$ 150,50', false)
            ->assertSee('04/2026', false)
            ->assertSee('Itens da fatura', false)
            ->assertSee('id="statement-cycle-', false)
            ->assertSee('data-statement-cycle-key=', false)
            ->assertSee('window.__invoiceCycleLinesByKey', false)
            ->getContent();

        $this->assertStringContainsString($card->id.'-2026-4', $html);
    }

    public function test_index_filtra_por_cartao(): void
    {
        extract($this->seedCoupleWithAccounts());

        $card2 = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Master',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#444',
        ]);

        $this->cardExpense($user, $card, $category, 4, 2026, '100.00');
        $this->cardExpense($user, $card2, $category, 5, 2026, '200.00');

        $this->actingAs($user)->get(route('credit-card-statements.index'))
            ->assertOk()
            ->assertDontSee('id="statement-cycle-', false);

        $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => $card->id]))
            ->assertOk()
            ->assertSee('statement-cycle-'.$card->id.'-2026-4', false)
            ->assertDontSee('statement-cycle-'.$card2->id.'-', false);

        $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => $card2->id]))
            ->assertOk()
            ->assertSee('statement-cycle-'.$card2->id.'-2026-5', false)
            ->assertDontSee('statement-cycle-'.$card->id.'-2026-4', false);

        $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => 9_999_999]))
            ->assertOk()
            ->assertDontSee('id="statement-cycle-', false);
    }

    public function test_index_alerta_quando_ha_fatura_mes_anterior_em_aberto(): void
    {
        extract($this->seedCoupleWithAccounts());

        Carbon::setTestNow(Carbon::parse('2026-04-15', config('app.timezone')));

        try {
            $this->cardExpense($user, $card, $category, 3, 2026, '80.00');

            $this->actingAs($user)->get(route('credit-card-statements.index'))
                ->assertOk()
                ->assertSee('cc-pick-card-past-open', false)
                ->assertSee('Há faturas de meses anteriores em aberto', false);

            $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 3]), [
                'account_id' => $checking->id,
                'payment_method' => 'Pix',
                'paid_date' => '2026-04-01',
            ])->assertSessionHasNoErrors();

            $this->actingAs($user)->get(route('credit-card-statements.index'))
                ->assertOk()
                ->assertDontSee('cc-pick-card-past-open', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_index_sem_alerta_para_fatura_do_mes_corrente(): void
    {
        extract($this->seedCoupleWithAccounts());

        Carbon::setTestNow(Carbon::parse('2026-04-15', config('app.timezone')));

        try {
            $this->cardExpense($user, $card, $category, 4, 2026, '40.00');

            $this->actingAs($user)->get(route('credit-card-statements.index'))
                ->assertOk()
                ->assertDontSee('cc-pick-card-past-open', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_materialize_preserva_vencimento_ja_definido(): void
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
        ]);

        $this->cardExpense($user, $card, $category, 6, 2026, '10.00');

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 6)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertSame('2026-07-10', $meta->due_date->toDateString());
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

        $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => $card->id]))
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
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
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

    public function test_editar_apenas_vencimento_cria_ou_atualiza_metadados(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 4, 2026);

        $this->actingAs($user)->put(route('credit-card-statements.update', [$card, 2026, 4]), [
            'due_date' => '2026-05-10',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertNotNull($meta);
        $this->assertSame('2026-05-10', $meta->due_date->toDateString());
        $this->assertFalse($meta->isPaid());
        $this->assertNull($meta->paid_at);
    }

    public function test_gerar_lancamento_na_conta_marca_fatura_paga(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 2, 2026, '300.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 2]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-03-08',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertNotNull($meta);
        $this->assertSame(1, $meta->paymentTransactions()->count());
        $this->assertTrue($meta->isPaid());

        $tx = $meta->paymentTransactions()->first();
        $this->assertNotNull($tx);
        $this->assertSame($checking->id, $tx->account_id);
        $this->assertSame($invoiceCategory->id, (int) $tx->categorySplits()->value('category_id'));
        $this->assertEquals(300.0, (float) $tx->categorySplits()->value('amount'));
        $this->assertEquals(300.0, (float) $tx->amount);
        $this->assertStringContainsString('Pagamento fatura', $tx->description);
    }

    public function test_excluir_lancamento_pagamento_fatura_atualiza_metadados(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 5, 2026, '10.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 5]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-06-01',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertTrue($meta->isPaid());
        $payTx = $meta->paymentTransactions()->first();
        $this->assertNotNull($payTx);

        $this->actingAs($user)->delete(route('transactions.destroy', $payTx), [
            'installment_scope' => 'single',
        ])->assertSessionHasNoErrors();

        $meta->refresh();
        $this->assertSame(0, $meta->paymentTransactions()->count());
        $this->assertNull($meta->paid_at);
        $this->assertFalse($meta->isPaid());
    }

    public function test_segundo_lancamento_parcial_quita_fatura(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 7, 2026, '100.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 7]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-08-02',
            'amount' => '50',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::first();
        $this->assertFalse($meta->isFullyPaidByPayments());
        $this->assertNull($meta->paid_at);
        $this->assertEquals(50.0, $meta->paymentsTotal());

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 7]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-08-03',
            'amount' => '50',
        ])->assertSessionHasNoErrors();

        $meta->refresh();
        $this->assertTrue($meta->isFullyPaidByPayments());
        $this->assertNotNull($meta->paid_at);
        $this->assertEquals(100.0, $meta->paymentsTotal());
    }

    public function test_nao_permite_novo_pagamento_apos_quitar_por_lancamentos(): void
    {
        extract($this->seedCoupleWithAccounts());
        $this->cardExpense($user, $card, $category, 9, 2026, '10.00');

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 9]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-10-02',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 9]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-10-03',
        ])->assertSessionHasErrors('payment');
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

    public function test_cadastrar_fatura_avulsa_aparece_na_lista_mesmo_sem_itens(): void
    {
        extract($this->seedCoupleWithAccounts());

        $this->actingAs($user)->post(route('credit-card-statements.store-avulsa', [$card]), [
            'reference_month' => 1,
            'reference_year' => 2026,
            'spent_total' => '123,45',
            'due_date' => '2026-01-20',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('credit_card_statements', [
            'account_id' => $card->id,
            'reference_month' => 1,
            'reference_year' => 2026,
            'is_avulsa' => true,
        ]);

        $this->actingAs($user)->get(route('credit-card-statements.index', ['account_id' => $card->id]))
            ->assertOk()
            ->assertSee('01/2026', false)
            ->assertSee('R$ 123,45', false)
            ->assertSee('Excluir', false);
    }

    public function test_pagamento_em_fatura_avulsa_usa_total_do_meta(): void
    {
        extract($this->seedCoupleWithAccounts());

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 2,
            'reference_year' => 2026,
            'spent_total' => '200.00',
            'due_date' => '2026-02-15',
            'paid_at' => null,
            'is_avulsa' => true,
        ]);

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 2]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-02-10',
        ])->assertSessionHasNoErrors();

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 2)
            ->where('reference_year', 2026)
            ->first();

        $this->assertNotNull($meta);
        $this->assertTrue($meta->isPaid());
        $this->assertEquals(200.0, $meta->paymentsTotal());
    }

    public function test_bloqueia_compras_em_ciclo_com_fatura_avulsa_inclusive_parcelado(): void
    {
        extract($this->seedCoupleWithAccounts());

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 4,
            'reference_year' => 2026,
            'spent_total' => '100.00',
            'due_date' => null,
            'paid_at' => null,
            'is_avulsa' => true,
        ]);

        // À vista no ciclo bloqueado.
        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'account_id' => $card->id,
            'description' => 'Compra bloqueada',
            'amount' => '10.00',
            'type' => 'expense',
            'date' => '2026-03-10',
            'installments' => 1,
            'reference_month' => 4,
            'reference_year' => 2026,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '10.00'],
            ],
        ])->assertSessionHasErrors('reference_month');

        // Parcelado: segunda parcela cairia no ciclo bloqueado.
        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 6,
            'reference_year' => 2026,
            'spent_total' => '100.00',
            'due_date' => null,
            'paid_at' => null,
            'is_avulsa' => true,
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'account_id' => $card->id,
            'description' => 'Compra parcelada bloqueada',
            'amount' => '20.00',
            'type' => 'expense',
            'date' => '2026-04-10',
            'installments' => 2,
            'reference_month' => 5,
            'reference_year' => 2026,
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '20.00'],
            ],
        ])->assertSessionHasErrors('reference_month');
    }

    public function test_store_avulsa_promove_meta_existente_sem_itens(): void
    {
        extract($this->seedCoupleWithAccounts());

        // Meta existente (ex.: criado por edição de vencimento no passado), mas sem itens nem pagamentos.
        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 10,
            'reference_year' => 2026,
            'spent_total' => '0.00',
            'due_date' => null,
            'paid_at' => null,
            'is_avulsa' => false,
        ]);

        $this->actingAs($user)->post(route('credit-card-statements.store-avulsa', [$card]), [
            'reference_month' => 10,
            'reference_year' => 2026,
            'spent_total' => '321,00',
            'due_date' => '2026-10-20',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('credit_card_statements', [
            'account_id' => $card->id,
            'reference_month' => 10,
            'reference_year' => 2026,
            'is_avulsa' => true,
        ]);

        $meta = CreditCardStatement::query()
            ->where('account_id', $card->id)
            ->where('reference_month', 10)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertTrue((bool) $meta->is_avulsa);
        $this->assertEquals(321.0, (float) $meta->spent_total);
        $this->assertSame('2026-10-20', $meta->due_date?->toDateString());
    }
}
