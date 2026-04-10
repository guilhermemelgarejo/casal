<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategorySplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_com_divisao_cria_duas_linhas_de_split(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $catA = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Cat A',
            'type' => 'expense',
            'color' => '#111111',
        ]);
        $catB = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Cat B',
            'type' => 'expense',
            'color' => '#222222',
        ]);
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'use_category_split' => '1',
            'category_allocations' => [
                ['category_id' => $catA->id, 'amount' => '60.00'],
                ['category_id' => $catB->id, 'amount' => '40.00'],
            ],
            'account_id' => $account->id,
            'description' => 'Compra mista',
            'amount' => '100.00',
            'type' => 'expense',
            'date' => '2026-04-09',
            'reference_month' => 4,
            'reference_year' => 2026,
        ])->assertSessionHasNoErrors();

        $tx = Transaction::query()->where('couple_id', $couple->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame(2, $tx->categorySplits()->count());
        $this->assertEqualsWithDelta(60.0, (float) $tx->categorySplits()->where('category_id', $catA->id)->value('amount'), 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $tx->categorySplits()->where('category_id', $catB->id)->value('amount'), 0.001);
    }

    public function test_parcelamento_cartao_replica_proporcao_em_cada_parcela(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $catA = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Cat A',
            'type' => 'expense',
            'color' => '#111111',
        ]);
        $catB = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Cat B',
            'type' => 'expense',
            'color' => '#222222',
        ]);
        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#444444',
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'use_category_split' => '1',
            'category_allocations' => [
                ['category_id' => $catA->id, 'amount' => '50.00'],
                ['category_id' => $catB->id, 'amount' => '50.00'],
            ],
            'account_id' => $card->id,
            'description' => 'Parcelado',
            'amount' => '100.00',
            'installments' => '3',
            'type' => 'expense',
            'date' => '2026-04-09',
            'reference_month' => 5,
            'reference_year' => 2026,
        ])->assertSessionHasNoErrors();

        $parcels = Transaction::query()->where('couple_id', $couple->id)->orderBy('id')->get();
        $this->assertCount(3, $parcels);

        $totalA = 0;
        $totalB = 0;
        foreach ($parcels as $p) {
            $this->assertSame(2, $p->categorySplits()->count());
            $totalA += (float) $p->categorySplits()->where('category_id', $catA->id)->value('amount');
            $totalB += (float) $p->categorySplits()->where('category_id', $catB->id)->value('amount');
        }
        $this->assertEqualsWithDelta(50.0, $totalA, 0.02);
        $this->assertEqualsWithDelta(50.0, $totalB, 0.02);
    }

    public function test_store_aceita_ate_cinco_categorias(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $cats = [];
        for ($i = 0; $i < 5; $i++) {
            $cats[] = Category::create([
                'couple_id' => $couple->id,
                'name' => 'C'.$i,
                'type' => 'expense',
                'color' => '#111111',
            ]);
        }
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
        ]);

        $allocations = [];
        foreach ($cats as $c) {
            $allocations[] = ['category_id' => $c->id, 'amount' => '20.00'];
        }

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'category_allocations' => $allocations,
            'account_id' => $account->id,
            'description' => 'Cinco categorias',
            'amount' => '100.00',
            'type' => 'expense',
            'date' => '2026-04-09',
            'reference_month' => 4,
            'reference_year' => 2026,
        ])->assertSessionHasNoErrors();

        $tx = Transaction::query()->where('couple_id', $couple->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame(5, $tx->categorySplits()->count());
    }
}
