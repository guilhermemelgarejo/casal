<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filtra_listagem_por_conta(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Teste',
            'type' => 'expense',
            'color' => '#000000',
        ]);

        $accA = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Alfa',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);

        $accB = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Beta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#222222',
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $accA->id,
            'description' => 'Lançamento exclusivo conta Alfa',
            'amount' => 10,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-15',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '10.00']]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $accB->id,
            'description' => 'Lançamento exclusivo conta Beta',
            'amount' => 20,
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-16',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $category->id, 'amount' => '20.00']]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'month' => 4,
            'year' => 2026,
            'account_id' => $accA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Lançamento exclusivo conta Alfa', false);
        $response->assertDontSee('Lançamento exclusivo conta Beta', false);
    }

    public function test_index_rejeita_conta_de_outro_casal_no_filtro(): void
    {
        $coupleA = Couple::factory()->create();
        $coupleB = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $coupleA->id]);

        $foreignAccount = Account::create([
            'couple_id' => $coupleB->id,
            'name' => 'Conta estrangeira',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'account_id' => $foreignAccount->id,
        ]));

        $response->assertSessionHasErrors('account_id');
    }
}
