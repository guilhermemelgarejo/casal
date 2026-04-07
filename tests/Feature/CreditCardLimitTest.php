<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{couple: Couple, user: User, card: Account, checking: Account, category: Category, invoiceCategory: Category}
     */
    private function seedCoupleWithCardAndLimit(string $limitTotal = '1000.00'): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000000',
        ]);
        $card->forceFill(['credit_card_limit_total' => $limitTotal])->save();
        $card->recalculateCreditCardLimitAvailable();

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222222',
        ]);

        $invoiceCategory = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#333333',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        return compact('couple', 'user', 'card', 'checking', 'category', 'invoiceCategory');
    }

    public function test_cadastro_cartao_sem_limite_opcional(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $this->actingAs($user)->post(route('accounts.store'), [
            '_form' => 'account-store',
            'name' => 'Nubank',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#4f46e5',
        ])->assertSessionHasNoErrors();

        $acc = Account::query()->where('couple_id', $couple->id)->where('name', 'Nubank')->first();
        $this->assertNotNull($acc);
        $this->assertNull($acc->credit_card_limit_total);
        $this->assertNull($acc->credit_card_limit_available);
    }

    public function test_despesa_no_cartao_reduz_limite_disponivel_materializado(): void
    {
        extract($this->seedCoupleWithCardAndLimit('1000.00'));

        $this->assertSame('1000.00', $card->fresh()->credit_card_limit_available);

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '250.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $this->assertSame('750.00', $card->fresh()->credit_card_limit_available);
    }

    public function test_limite_disponivel_pode_ficar_negativo(): void
    {
        extract($this->seedCoupleWithCardAndLimit('1000.00'));

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra grande',
            'amount' => '1200.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $this->assertSame('-200.00', $card->fresh()->credit_card_limit_available);
    }

    public function test_pagamento_parcial_de_fatura_aumenta_limite_disponivel(): void
    {
        extract($this->seedCoupleWithCardAndLimit('1000.00'));

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '400.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $this->assertSame('600.00', $card->fresh()->credit_card_limit_available);

        $this->actingAs($user)->post(route('credit-card-statements.attach-payment', [$card, 2026, 4]), [
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'paid_date' => '2026-05-12',
            'amount' => '150',
        ])->assertSessionHasNoErrors();

        $this->assertSame('750.00', $card->fresh()->credit_card_limit_available);
    }

    public function test_alterar_limite_total_recalcula_disponivel(): void
    {
        extract($this->seedCoupleWithCardAndLimit('1000.00'));

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '400.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $this->assertSame('600.00', $card->fresh()->credit_card_limit_available);

        $this->actingAs($user)->put(route('accounts.update', $card), [
            '_form' => 'account-update-'.$card->id,
            'name' => 'Visa',
            'color' => '#000000',
            'credit_card_invoice_due_day' => 10,
            'credit_card_limit_total' => '800',
        ])->assertSessionHasNoErrors();

        $card->refresh();
        $this->assertSame('800.00', $card->credit_card_limit_total);
        $this->assertSame('400.00', $card->credit_card_limit_available);
    }

    public function test_store_lancamento_ultrapassa_limite_exige_segundo_envio_com_token(): void
    {
        extract($this->seedCoupleWithCardAndLimit('1000.00'));

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '600.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        $payload = [
            'funding' => 'credit_card',
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Outra compra',
            'amount' => '500',
            'type' => 'expense',
            'date' => '2026-04-15',
            'installments' => 1,
            'reference_month' => 4,
            'reference_year' => 2026,
        ];

        $this->actingAs($user)->post(route('transactions.store'), $payload)
            ->assertSessionHas('credit_limit_overflow')
            ->assertSessionMissing('success');

        $token = session('credit_limit_overflow')['token'];
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $this->actingAs($user)->post(route('transactions.store'), array_merge($payload, [
            'credit_limit_confirm_token' => $token,
        ]))->assertSessionHas('success');

        $this->assertSame('-100.00', $card->fresh()->credit_card_limit_available);
    }
}
