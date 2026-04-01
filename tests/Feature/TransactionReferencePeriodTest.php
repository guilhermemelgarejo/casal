<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
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
            'category_id' => $category->id,
            'account_id' => $account->id,
            'description' => 'Compra',
            'amount' => 100,
            'payment_method' => 'Cartão de Crédito',
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
            'allowed_payment_methods' => ['Pix', 'Cartão de Crédito'], // mesmo que tentem salvar, deve ser efetivamente bloqueado
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
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
}

