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

class CreditCardRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_estorno_no_cartao_reduz_spent_total_da_fatura(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000000',
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Loja',
            'type' => 'expense',
            'color' => '#222222',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'type' => 'expense',
            'date' => '2026-04-10',
            'installments' => 1,
            'reference_month' => 4,
            'reference_year' => 2026,
            'category_allocations' => [
                ['category_id' => $cat->id, 'amount' => '100.00'],
            ],
        ])->assertSessionHasNoErrors();

        $purchase = Transaction::query()->where('couple_id', $couple->id)->first();
        $this->assertNotNull($purchase);

        $meta = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($meta);
        $this->assertEquals(100.0, (float) $meta->spent_total);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'account_id' => $card->id,
            'description' => 'Estorno parcial',
            'amount' => '45.00',
            'type' => 'expense',
            'date' => '2026-04-12',
            'installments' => 1,
            'reference_month' => 4,
            'reference_year' => 2026,
            'is_refund' => 1,
            'refund_of_transaction_id' => $purchase->id,
            'category_allocations' => [
                ['category_id' => $cat->id, 'amount' => '45.00'],
            ],
        ])->assertSessionHasNoErrors();

        $meta->refresh();
        $this->assertEquals(55.0, (float) $meta->spent_total);
    }

    public function test_estorno_persiste_amount_e_splits_negativos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000000',
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Categoria',
            'type' => 'expense',
            'color' => '#222222',
        ]);

        $purchase = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '100.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $cat->id, 'amount' => '100.00']]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'account_id' => $card->id,
            'description' => 'Estorno',
            'amount' => '30.00',
            'type' => 'expense',
            'date' => '2026-04-11',
            'installments' => 1,
            'reference_month' => 4,
            'reference_year' => 2026,
            'is_refund' => 1,
            'refund_of_transaction_id' => $purchase->id,
            'category_allocations' => [
                ['category_id' => $cat->id, 'amount' => '30.00'],
            ],
        ])->assertSessionHasNoErrors();

        $refund = Transaction::query()
            ->where('couple_id', $couple->id)
            ->whereNotNull('refund_of_transaction_id')
            ->first();

        $this->assertNotNull($refund);
        $this->assertEquals(-30.0, (float) $refund->amount);
        $this->assertSame($purchase->id, (int) $refund->refund_of_transaction_id);
        $this->assertEquals(-30.0, (float) $refund->categorySplits()->value('amount'));
    }
}

