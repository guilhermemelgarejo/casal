<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionInstallmentDeleteMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exibe_metadado_peer_count_para_parcelamento_em_outro_mes(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
            'allowed_payment_methods' => null,
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Loja',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $parent = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 1/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ]);

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 2/2)',
            'amount' => '50.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-05-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'month' => 4,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('&quot;peerCount&quot;:2', $response->getContent());
        $this->assertStringContainsString('&quot;singleAllowed&quot;:false', $response->getContent());
    }
}
