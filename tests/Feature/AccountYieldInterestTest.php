<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountYieldInterestTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoupleUser(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        return compact('couple', 'user');
    }

    public function test_account_can_be_created_with_yields_interest(): void
    {
        ['user' => $user] = $this->seedCoupleUser();

        $this->actingAs($user)->post(route('accounts.store'), [
            '_form' => 'account-store',
            'name' => 'Nubank Caixinha',
            'kind' => Account::KIND_REGULAR,
            'yields_interest' => '1',
            'color' => '#820ad1',
        ])->assertSessionHas('success');

        $account = Account::where('name', 'Nubank Caixinha')->first();
        $this->assertNotNull($account);
        $this->assertTrue($account->yields_interest);
        $this->assertTrue($account->yieldsInterest());
    }

    public function test_credit_card_ignores_yields_interest_on_store(): void
    {
        ['user' => $user] = $this->seedCoupleUser();

        $this->actingAs($user)->post(route('accounts.store'), [
            '_form' => 'account-store',
            'name' => 'Nubank Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'yields_interest' => '1',
            'color' => '#820ad1',
            'credit_card_invoice_due_day' => 10,
        ])->assertSessionHas('success');

        $account = Account::where('name', 'Nubank Cartão')->first();
        $this->assertNotNull($account);
        $this->assertFalse($account->yields_interest);
        $this->assertFalse($account->yieldsInterest());
    }

    public function test_account_yields_interest_can_be_updated(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedCoupleUser();

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Itaú',
            'kind' => Account::KIND_REGULAR,
            'yields_interest' => false,
            'color' => '#ec7000',
        ]);

        $this->assertFalse($account->yieldsInterest());

        $this->actingAs($user)->put(route('accounts.update', $account), [
            '_form' => 'account-update-'.$account->id,
            'name' => 'Itaú Rendimentos',
            'color' => '#ec7000',
            'yields_interest' => '1',
        ])->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->yields_interest);
        $this->assertTrue($account->yieldsInterest());
    }

    public function test_store_interest_adds_income_transaction_and_updates_account_balance(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedCoupleUser();

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado Pago',
            'kind' => Account::KIND_REGULAR,
            'yields_interest' => true,
            'color' => '#009ee3',
        ]);
        $account->forceFill(['balance' => '500.00'])->save();

        $response = $this->actingAs($user)->post(route('accounts.interest.store', $account), [
            'amount' => '12.50',
            'date' => '2026-08-20',
            'description' => 'Rendimento CDI Agosto',
        ]);

        $response->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('512.50', number_format((float) $account->balance, 2, '.', ''));

        $tx = Transaction::where('account_id', $account->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('income', $tx->type);
        $this->assertSame('12.50', number_format((float) $tx->amount, 2, '.', ''));
        $this->assertSame('Rendimento CDI Agosto', $tx->description);
        $this->assertSame('2026-08-20', $tx->date->toDateString());
        $this->assertSame(8, (int) $tx->reference_month);
        $this->assertSame(2026, (int) $tx->reference_year);

        // Check category split
        $category = Category::where('couple_id', $couple->id)
            ->where('system_key', Category::SYSTEM_KEY_ACCOUNT_YIELD)
            ->first();
        $this->assertNotNull($category);
        $this->assertSame(Category::NAME_ACCOUNT_YIELD, $category->name);

        $split = $tx->categorySplits()->first();
        $this->assertNotNull($split);
        $this->assertSame($category->id, $split->category_id);
        $this->assertSame('12.50', number_format((float) $split->amount, 2, '.', ''));
    }

    public function test_cannot_store_interest_on_account_without_yields_interest(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedCoupleUser();

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Carteira',
            'kind' => Account::KIND_REGULAR,
            'yields_interest' => false,
            'color' => '#111111',
        ]);
        $account->forceFill(['balance' => '100.00'])->save();

        $this->actingAs($user)->post(route('accounts.interest.store', $account), [
            'amount' => '10.00',
            'date' => '2026-08-20',
        ])->assertSessionHas('error');

        $account->refresh();
        $this->assertSame('100.00', number_format((float) $account->balance, 2, '.', ''));
        $this->assertDatabaseMissing('transactions', ['account_id' => $account->id]);
    }

    public function test_cannot_store_interest_on_account_of_another_couple(): void
    {
        ['user' => $user] = $this->seedCoupleUser();

        $otherCouple = Couple::factory()->create();
        $otherAccount = Account::create([
            'couple_id' => $otherCouple->id,
            'name' => 'Outra Conta',
            'kind' => Account::KIND_REGULAR,
            'yields_interest' => true,
            'color' => '#333333',
        ]);
        $otherAccount->forceFill(['balance' => '100.00'])->save();

        $this->actingAs($user)->post(route('accounts.interest.store', $otherAccount), [
            'amount' => '50.00',
            'date' => '2026-08-20',
        ])->assertStatus(403);

        $otherAccount->refresh();
        $this->assertSame('100.00', number_format((float) $otherAccount->balance, 2, '.', ''));
    }

    public function test_accounts_are_sorted_by_balance_desc_and_credit_cards_by_current_invoice_desc(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->seedCoupleUser();

        // Contas regulares
        $accSmall = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Carteira',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);
        $accSmall->forceFill(['balance' => '50.00'])->save();

        $accBig = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Mercado Pago',
            'kind' => Account::KIND_REGULAR,
            'color' => '#009ee3',
        ]);
        $accBig->forceFill(['balance' => '500.00'])->save();

        // Cartões de crédito
        $cardLowInvoice = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão Itaú',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#ec7000',
        ]);

        $cardHighInvoice = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão Bradesco',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#cc092f',
        ]);

        // Lança despesas no mês/ano atual
        $now = now();
        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $cardLowInvoice->id,
            'type' => 'expense',
            'amount' => '100.00',
            'date' => $now->toDateString(),
            'reference_month' => $now->month,
            'reference_year' => $now->year,
            'description' => 'Despesa baixa',
        ]);

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $cardHighInvoice->id,
            'type' => 'expense',
            'amount' => '1500.00',
            'date' => $now->toDateString(),
            'reference_month' => $now->month,
            'reference_year' => $now->year,
            'description' => 'Despesa alta',
        ]);

        $response = $this->actingAs($user)->get(route('accounts.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $accounts */
        $accounts = $response->viewData('accounts');

        $regularList = $accounts->where('kind', Account::KIND_REGULAR)->values();
        $this->assertSame($accBig->id, $regularList[0]->id);
        $this->assertSame($accSmall->id, $regularList[1]->id);

        $cardList = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();
        $this->assertSame($cardHighInvoice->id, $cardList[0]->id);
        $this->assertSame($cardLowInvoice->id, $cardList[1]->id);
    }
}
