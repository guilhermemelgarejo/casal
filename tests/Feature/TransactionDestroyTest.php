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

class TransactionDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_permite_excluir_despesa_no_cartao_em_ciclo_de_fatura_ja_pago(): void
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
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $purchase = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra no cartão',
            'amount' => '80.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 4,
            'reference_year' => 2026,
            'due_date' => null,
            'paid_at' => '2026-04-20',
            'payment_transaction_id' => null,
        ]);

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $purchase), [
            'installment_scope' => 'single',
        ]);

        $response->assertSessionHas('error');
        $this->assertTrue(Transaction::query()->whereKey($purchase->id)->exists());
    }

    public function test_permite_excluir_despesa_no_cartao_se_fatura_do_ciclo_nao_esta_paga(): void
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
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $purchase = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '40.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-05',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 4,
            'reference_year' => 2026,
            'due_date' => null,
            'paid_at' => null,
            'payment_transaction_id' => null,
        ]);

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $purchase), [
            'installment_scope' => 'single',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse(Transaction::query()->whereKey($purchase->id)->exists());
    }

    public function test_permite_excluir_quitacao_na_conta_mesmo_com_fatura_marcada_paga(): void
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

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
            'allowed_payment_methods' => null,
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $payment = Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura',
            'amount' => '500.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
        ]);

        CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 3,
            'reference_year' => 2026,
            'due_date' => null,
            'paid_at' => '2026-04-10',
            'payment_transaction_id' => $payment->id,
        ]);

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $payment), [
            'installment_scope' => 'single',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse(Transaction::query()->whereKey($payment->id)->exists());
        $meta = CreditCardStatement::first();
        $this->assertNull($meta->payment_transaction_id);
        $this->assertNull($meta->paid_at);
    }

    public function test_excluir_somente_primeira_parcela_com_irmaos_e_rejeitado(): void
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

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $parent), [
            'installment_scope' => 'single',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(2, Transaction::query()->where('couple_id', $couple->id)->count());
    }

    public function test_excluir_parcela_filha_mantem_irmaos(): void
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

        $child = Transaction::create([
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

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $child), [
            'installment_scope' => 'single',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Transaction::query()->whereKey($parent->id)->exists());
        $this->assertFalse(Transaction::query()->whereKey($child->id)->exists());
    }

    public function test_installment_scope_all_remove_grupo_inteiro_a_partir_da_segunda_parcela(): void
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

        $child = Transaction::create([
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

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $child), [
            'installment_scope' => 'all',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, Transaction::query()->where('couple_id', $couple->id)->count());
    }
}
