<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class FinancialIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $account = Account::create(['couple_id' => $couple->id, 'name' => 'Conta teste', 'kind' => 'regular']);
        $category = Category::create(['couple_id' => $couple->id, 'name' => 'Despesa teste', 'type' => 'expense']);

        return compact('couple', 'user', 'account', 'category');
    }

    private function expense(array $fixture, string $amount = '100.00'): Transaction
    {
        return $this->createTransactionWithSplits([
            'couple_id' => $fixture['couple']->id, 'user_id' => $fixture['user']->id,
            'account_id' => $fixture['account']->id, 'description' => 'Despesa compartilhada',
            'amount' => $amount, 'type' => 'expense', 'date' => '2026-10-23',
            'reference_month' => 10, 'reference_year' => 2026,
        ], [['category_id' => $fixture['category']->id, 'amount' => $amount]]);
    }

    public function test_profile_deletion_preserves_shared_transactions_balances_splits_and_payments(): void
    {
        $f = $this->fixture();
        $partner = User::factory()->create(['couple_id' => $f['couple']->id]);
        $tx = $this->expense($f);
        $card = Account::create(['couple_id' => $f['couple']->id, 'name' => 'Cartão teste', 'kind' => 'credit_card']);
        $statement = CreditCardStatement::create([
            'couple_id' => $f['couple']->id, 'account_id' => $card->id,
            'reference_month' => 10, 'reference_year' => 2026, 'spent_total' => '100.00', 'is_avulsa' => true,
        ]);
        $statement->paymentTransactions()->attach($tx);
        $statement->syncPaidMetadata();

        $this->actingAs($f['user'])->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(User::find($f['user']->id));
        $this->assertSame($f['user']->id, $tx->fresh()->user_id);
        $this->assertNull($tx->fresh()->user);
        $this->assertSame('Usuário excluído', DB::table('users')->where('id', $f['user']->id)->value('name'));
        $this->assertNull(DB::table('users')->where('id', $f['user']->id)->value('remember_token'));
        $this->assertFalse(Hash::check('password', DB::table('users')->where('id', $f['user']->id)->value('password')));
        $this->assertNull(Auth::guard('web')->getProvider()->retrieveById($f['user']->id));
        $this->assertSame(1, $tx->fresh()->categorySplits()->count());
        $this->assertSame('-100.00', $f['account']->fresh()->balance);
        $this->assertSame(-100.0, Account::balancesFromTransactionsByAccountId([$f['account']->id])[$f['account']->id]);
        $this->assertTrue($statement->fresh()->isPaid());
        $this->assertSame($f['couple']->id, $partner->fresh()->couple_id);
        $this->actingAs($partner)->get('/transactions?month=10&year=2026')->assertOk()->assertSee('Usuário excluído');
    }

    public function test_model_user_deletion_preserves_authored_transactions_even_with_cascade_foreign_key(): void
    {
        $f = $this->fixture();
        $tx = $this->expense($f);
        $f['user']->delete();
        $this->assertNotNull($tx->fresh());
        $this->assertSame($f['user']->id, $tx->fresh()->user_id);
        $this->assertNull(User::find($f['user']->id));
        $this->assertSame('-100.00', $f['account']->fresh()->balance);
    }

    public function test_billing_owner_cannot_delete_profile_to_bypass_transfer_rule(): void
    {
        $f = $this->fixture();
        User::factory()->create(['couple_id' => $f['couple']->id]);
        $f['couple']->forceFill(['billing_owner_user_id' => $f['user']->id])->save();
        $this->actingAs($f['user'])->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'membership');
        $this->assertAuthenticated();
        $this->assertSame($f['couple']->id, $f['user']->fresh()->couple_id);
    }

    #[DataProvider('nonTerminalSubscriptionStatuses')]
    public function test_subscriber_cannot_delete_profile_and_orphan_stripe_subscription(string $status): void
    {
        $f = $this->fixture();
        $f['user']->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_profile_test', 'stripe_status' => $status,
            'stripe_price' => 'price_test', 'quantity' => 1,
        ]);
        $this->actingAs($f['user'])->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'subscription');
        $this->assertSame($f['couple']->id, $f['user']->fresh()->couple_id);
        $this->assertSame(1, $f['user']->subscriptions()->count());
    }

    public static function nonTerminalSubscriptionStatuses(): array
    {
        return [['active'], ['past_due'], ['unpaid'], ['incomplete'], ['paused']];
    }

    public function test_debt_payment_rejects_retries_without_extra_debits_or_replacing_link(): void
    {
        $f = $this->fixture();
        $debt = Debt::create([
            'couple_id' => $f['couple']->id, 'name' => 'Dívida teste', 'type' => 'installments',
            'total_amount' => '100.00', 'total_installments' => 1, 'default_category_id' => $f['category']->id,
        ]);
        $installment = DebtInstallment::create([
            'couple_id' => $f['couple']->id, 'debt_id' => $debt->id,
            'installment_number' => 1, 'amount' => '100.00', 'status' => 'pending',
        ]);
        $payload = ['account_id' => $f['account']->id, 'amount' => '100.00', 'paid_at' => '2026-10-23'];
        $this->actingAs($f['user'])->post(route('debts.installments.pay', $installment), $payload)
            ->assertSessionHasNoErrors();
        $transactionId = $installment->fresh()->transaction_id;
        $this->post(route('debts.installments.pay', $installment), $payload)->assertSessionHasErrors('payment');

        $this->assertSame(1, Transaction::count());
        $this->assertSame($transactionId, $installment->fresh()->transaction_id);
        $this->assertSame('-100.00', $f['account']->fresh()->balance);
        $this->assertSame('100.00', $installment->fresh()->paid_amount);
    }

    public function test_cancelled_installment_cannot_generate_a_payment(): void
    {
        $f = $this->fixture();
        $debt = Debt::create(['couple_id' => $f['couple']->id, 'name' => 'Dívida', 'total_amount' => '100.00']);
        $installment = DebtInstallment::create([
            'couple_id' => $f['couple']->id, 'debt_id' => $debt->id, 'status' => 'cancelled', 'amount' => '100.00',
        ]);
        $this->actingAs($f['user'])->post(route('debts.installments.pay', $installment), [
            'account_id' => $f['account']->id, 'amount' => '100.00', 'paid_at' => '2026-10-23',
        ])->assertSessionHasErrors('payment');
        $this->assertSame(0, Transaction::count());
        $this->assertSame('0.00', $f['account']->fresh()->balance);
    }
}
