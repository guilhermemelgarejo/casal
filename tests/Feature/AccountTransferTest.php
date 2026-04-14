<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTransferTest extends TestCase
{
    use RefreshDatabase;

    private function coupleWithTwoRegularAccounts(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $from = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta A',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);
        $to = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta B',
            'kind' => Account::KIND_REGULAR,
            'color' => '#222222',
        ]);

        $catIncome = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Entrada teste',
            'type' => 'income',
            'color' => '#000000',
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $from->id,
            'description' => 'Saldo inicial',
            'amount' => 1000,
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => '2026-04-01',
            'reference_month' => 4,
            'reference_year' => 2026,
        ], [['category_id' => $catIncome->id, 'amount' => '1000.00']]);

        Category::ensureInternalTransferCategoriesForCouple((int) $couple->id);

        return [$couple, $user, $from, $to];
    }

    public function test_transferencia_entre_contas_atualiza_saldos(): void
    {
        [$couple, $user, $from, $to] = $this->coupleWithTwoRegularAccounts();

        $this->assertSame('1000.00', $from->fresh()->balance);
        $this->assertSame('0.00', $to->fresh()->balance);

        $response = $this->actingAs($user)->post(route('accounts.transfer'), [
            '_form' => 'account-transfer',
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => '250,50',
            'date' => '2026-04-10',
            'payment_method' => 'Pix',
            'description' => 'Teste movimento',
        ], ['referer' => route('accounts.index')]);

        $response->assertRedirect(route('accounts.index'));
        $response->assertSessionHas('success');

        $this->assertSame('749.50', $from->fresh()->balance);
        $this->assertSame('250.50', $to->fresh()->balance);

        $pairs = Transaction::query()
            ->where('couple_id', $couple->id)
            ->whereNotNull('internal_transfer_group_id')
            ->get();
        $this->assertCount(2, $pairs);
        $this->assertSame($pairs[0]->internal_transfer_group_id, $pairs[1]->internal_transfer_group_id);
    }

    public function test_transferencia_rejeita_cartao_de_credito(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $regular = Account::create([
            'couple_id' => $couple->id,
            'name' => 'CC',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111111',
        ]);
        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#222222',
            'credit_card_invoice_due_day' => 10,
        ]);

        Category::ensureInternalTransferCategoriesForCouple((int) $couple->id);

        $response = $this->actingAs($user)->post(route('accounts.transfer'), [
            '_form' => 'account-transfer',
            'from_account_id' => $regular->id,
            'to_account_id' => $card->id,
            'amount' => '10',
            'date' => '2026-04-10',
            'payment_method' => 'Pix',
        ]);

        $response->assertSessionHasErrors('to_account_id');
    }

    public function test_excluir_um_lancamento_de_transferencia_remove_o_par(): void
    {
        [$couple, $user, $from, $to] = $this->coupleWithTwoRegularAccounts();

        $this->actingAs($user)->post(route('accounts.transfer'), [
            '_form' => 'account-transfer',
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => '100',
            'date' => '2026-04-10',
            'payment_method' => 'Pix',
        ]);

        $this->assertSame('900.00', $from->fresh()->balance);
        $this->assertSame('100.00', $to->fresh()->balance);

        $expense = Transaction::query()
            ->where('couple_id', $couple->id)
            ->where('type', 'expense')
            ->whereNotNull('internal_transfer_group_id')
            ->firstOrFail();

        $this->actingAs($user)->delete(route('transactions.destroy', $expense));

        $this->assertSame(0, Transaction::query()->whereNotNull('internal_transfer_group_id')->count());
        $this->assertSame('1000.00', $from->fresh()->balance);
        $this->assertSame('0.00', $to->fresh()->balance);
    }
}
