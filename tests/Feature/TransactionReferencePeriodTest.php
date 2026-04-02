<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReferencePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_lancamento_no_credito_pode_definir_mes_de_referencia_da_fatura(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'type' => 'expense',
            'color' => '#000000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000000',
            'allowed_payment_methods' => null, // todas
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'description' => 'Compra',
            'amount' => 100,
            'installments' => 2,
            'type' => 'expense',
            'date' => '2026-04-20',
            'reference_month' => 5,
            'reference_year' => 2026,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertTrue(Transaction::query()
            ->where('couple_id', $couple->id)
            ->where('description', 'Compra (Parcela 1/2)')
            ->whereDate('date', '2026-04-20')
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->exists());

        $this->assertTrue(Transaction::query()
            ->where('couple_id', $couple->id)
            ->where('description', 'Compra (Parcela 2/2)')
            ->whereDate('date', '2026-05-20')
            ->where('reference_month', 6)
            ->where('reference_year', 2026)
            ->exists());

        $this->assertSame(2, Transaction::query()->where('couple_id', $couple->id)->count());

        $this->assertTrue(Transaction::query()
            ->where('couple_id', $couple->id)
            ->whereNull('payment_method')
            ->exists());
    }

    public function test_conta_nao_cartao_nao_permite_pagamento_no_credito(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado',
            'type' => 'expense',
            'color' => '#000000',
        ]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Banco X',
            'kind' => Account::KIND_REGULAR,
            'color' => '#000000',
            'allowed_payment_methods' => ['Pix'],
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'category_id' => $category->id,
            'account_id' => $account->id,
            'description' => 'Compra',
            'amount' => 10,
            'payment_method' => 'Cartão de Crédito',
            'installments' => 1,
            'type' => 'expense',
            'date' => '2026-04-20',
        ]);

        $response->assertSessionHasErrors(['payment_method']);
        $this->assertSame(0, Transaction::query()->where('couple_id', $couple->id)->count());
    }

    public function test_cartao_nao_aceita_campo_forma_de_pagamento(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Loja',
            'type' => 'expense',
            'color' => '#000000',
        ]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000000',
            'allowed_payment_methods' => null,
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'X',
            'amount' => 5,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-01',
        ]);

        $response->assertSessionHasErrors(['payment_method']);
        $this->assertSame(0, Transaction::query()->where('couple_id', $couple->id)->count());
    }

    public function test_credito_sem_referencia_usa_mes_seguinte_a_hoje(): void
    {
        Carbon::setTestNow('2026-12-10 12:00:00');
        try {
            $couple = Couple::factory()->create();
            $user = User::factory()->create(['couple_id' => $couple->id]);

            $category = Category::create([
                'couple_id' => $couple->id,
                'name' => 'Compras',
                'type' => 'expense',
                'color' => '#000000',
            ]);

            $card = Account::create([
                'couple_id' => $couple->id,
                'name' => 'Master',
                'kind' => Account::KIND_CREDIT_CARD,
                'color' => '#000000',
                'allowed_payment_methods' => null,
            ]);

            $response = $this->actingAs($user)->post(route('transactions.store'), [
                'funding' => 'credit_card',
                'category_id' => $category->id,
                'account_id' => $card->id,
                'description' => 'Item',
                'amount' => 50,
                'installments' => 1,
                'type' => 'expense',
                'date' => '2025-01-01',
            ]);

            $response->assertSessionHasNoErrors();

            $this->assertTrue(Transaction::query()
                ->where('couple_id', $couple->id)
                ->where('description', 'Item')
                ->where('reference_month', 1)
                ->where('reference_year', 2027)
                ->exists());
        } finally {
            Carbon::setTestNow();
        }
    }
}

