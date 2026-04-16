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

class TransactionSkipInstallmentMonthTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoupleWithAccounts(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
        ]);

        $checking = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#111',
        ]);

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Outros',
            'type' => 'expense',
            'color' => '#222',
        ]);

        $invoiceCategory = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#333',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        return compact('couple', 'user', 'card', 'checking', 'category', 'invoiceCategory');
    }

    public function test_skip_month_desloca_parcelas_em_mais_um_mes(): void
    {
        extract($this->seedCoupleWithAccounts());

        $parent = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 1/2)',
            'amount' => '40.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '40.00']]);

        $child = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 2/2)',
            'amount' => '60.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-05-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ], [['category_id' => $category->id, 'amount' => '60.00']]);

        $this->actingAs($user)->post(route('transactions.skip-installment-month', $parent))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $parent->refresh();
        $child->refresh();

        $this->assertSame(5, (int) $parent->reference_month);
        $this->assertSame(6, (int) $child->reference_month);
        $this->assertSame(2026, (int) $parent->reference_year);
        $this->assertSame(2026, (int) $child->reference_year);

        $stmt4 = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $card->id)
            ->where('reference_month', 4)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmt4);
        $this->assertEquals(0.0, (float) $stmt4->spent_total);

        $stmt5 = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $card->id)
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmt5);
        $this->assertEquals(40.0, (float) $stmt5->spent_total);

        $stmt6 = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $card->id)
            ->where('reference_month', 6)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmt6);
        $this->assertEquals(60.0, (float) $stmt6->spent_total);
    }

    public function test_skip_month_bloqueia_se_uma_parcela_afetada_estiver_em_fatura_parcialmente_paga(): void
    {
        extract($this->seedCoupleWithAccounts());

        $parent = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 1/2)',
            'amount' => '40.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $category->id, 'amount' => '40.00']]);

        $child = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'description' => 'Compra (Parcela 2/2)',
            'amount' => '60.00',
            'payment_method' => null,
            'type' => 'expense',
            'date' => '2026-05-10',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => $parent->id,
        ], [['category_id' => $category->id, 'amount' => '60.00']]);

        $stmt5 = CreditCardStatement::query()
            ->where('couple_id', $couple->id)
            ->where('account_id', $card->id)
            ->where('reference_month', 5)
            ->where('reference_year', 2026)
            ->first();
        $this->assertNotNull($stmt5);

        $payment = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $checking->id,
            'description' => 'Pagamento fatura parcial',
            'amount' => '10.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-05-15',
            'reference_month' => 5,
            'reference_year' => 2026,
            'installment_parent_id' => null,
        ], [['category_id' => $invoiceCategory->id, 'amount' => '10.00']]);

        $stmt5->paymentTransactions()->attach($payment->id);
        $stmt5->syncPaidMetadata();

        $this->actingAs($user)->post(route('transactions.skip-installment-month', $parent))
            ->assertSessionHas('error');

        $parent->refresh();
        $child->refresh();

        $this->assertSame(4, (int) $parent->reference_month);
        $this->assertSame(5, (int) $child->reference_month);
    }
}

