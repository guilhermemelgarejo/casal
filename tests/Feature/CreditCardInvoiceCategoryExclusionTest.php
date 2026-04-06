<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardInvoiceCategoryExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_lancamento_rejeita_categoria_pagamento_fatura(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $catInvoice = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#64748b',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $regular = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $response = $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'category_id' => $catInvoice->id,
            'account_id' => $regular->id,
            'description' => 'Teste',
            'amount' => '10.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-05',
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('transactions', [
            'couple_id' => $couple->id,
            'category_id' => $catInvoice->id,
        ]);
    }

    public function test_store_orcamento_rejeita_categoria_pagamento_fatura(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $catInvoice = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#64748b',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $response = $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $catInvoice->id,
            'amount' => '100',
        ]);

        $response->assertSessionHasErrors('category_id');
    }
}
