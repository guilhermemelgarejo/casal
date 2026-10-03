<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    private function setupCoupleAndUser(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank Conta',
            'kind' => Account::KIND_REGULAR,
            'color' => '#820ad1',
        ]);
        $account->forceFill(['balance' => '5000.00'])->save();

        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento & Dívidas',
            'type' => 'expense',
            'color' => '#f59e0b',
        ]);

        return compact('couple', 'user', 'account', 'category');
    }

    public function test_user_can_view_debts_index_agenda_and_dividas_tabs(): void
    {
        ['user' => $user] = $this->setupCoupleAndUser();

        $responseAgenda = $this->actingAs($user)->get(route('debts.index', ['tab' => 'agenda']));
        $responseAgenda->assertOk();
        $responseAgenda->assertSee('Dívidas');
        $responseAgenda->assertSee('Vencimentos');
        $responseAgenda->assertSee('Agenda do Mês');

        $responseDividas = $this->actingAs($user)->get(route('debts.index', ['tab' => 'dividas']));
        $responseDividas->assertOk();
        $responseDividas->assertSee('Saldo Devedor Restante');
        $responseDividas->assertSee('Financiamentos');
    }

    public function test_user_can_create_installment_debt_and_installments_are_generated(): void
    {
        ['user' => $user, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $response = $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Financiamento Fiat Argo',
            'creditor' => 'Banco Santander',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => '12.000,00',
            'total_installments' => 12,
            'start_date' => '2026-09-15',
            'due_day' => 15,
            'default_account_id' => $account->id,
            'default_category_id' => $category->id,
            'color' => '#ef4444',
            'notes' => 'Contrato nº 123456',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'agenda']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('debts', [
            'name' => 'Financiamento Fiat Argo',
            'creditor' => 'Banco Santander',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 12000.00,
            'total_installments' => 12,
            'is_active' => true,
        ]);

        $debt = Debt::where('name', 'Financiamento Fiat Argo')->first();
        $this->assertNotNull($debt);
        $this->assertCount(12, $debt->installments);

        $firstInstallment = $debt->installments()->where('installment_number', 1)->first();
        $this->assertEquals('2026-09-15', $firstInstallment->due_date->format('Y-m-d'));
        $this->assertEquals('1000.00', $firstInstallment->amount);
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $firstInstallment->status);

        $secondInstallment = $debt->installments()->where('installment_number', 2)->first();
        $this->assertEquals('2026-10-15', $secondInstallment->due_date->format('Y-m-d'));
    }

    public function test_user_can_create_free_debt_without_predefined_schedule(): void
    {
        ['user' => $user] = $this->setupCoupleAndUser();

        $response = $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Empréstimo Tio Carlos',
            'creditor' => 'Tio Carlos',
            'type' => Debt::TYPE_FREE,
            'total_amount' => '3.500,00',
            'color' => '#3b82f6',
            'notes' => 'Pagar conforme sobrar dinheiro',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));

        $debt = Debt::where('name', 'Empréstimo Tio Carlos')->first();
        $this->assertNotNull($debt);
        $this->assertTrue($debt->isFree());
        $this->assertEquals(3500.00, (float) $debt->total_amount);
        $this->assertCount(0, $debt->installments);
        $this->assertEquals(3500.00, $debt->remainingBalance());
        $this->assertEquals(0.0, $debt->progressPercentage());
    }

    public function test_user_can_update_debt_metadata(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Inicial',
            'type' => Debt::TYPE_FREE,
            'total_amount' => 1000.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('debts.update', $debt), [
            'name' => 'Dívida Atualizada',
            'creditor' => 'Credor X',
            'color' => '#10b981',
            'notes' => 'Notas atualizadas',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));
        $this->assertDatabaseHas('debts', [
            'id' => $debt->id,
            'name' => 'Dívida Atualizada',
            'creditor' => 'Credor X',
            'is_active' => false,
        ]);
    }

    public function test_user_can_toggle_debt_active_status(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Teste',
            'type' => Debt::TYPE_FREE,
            'total_amount' => 500.00,
            'is_active' => true,
        ]);

        $this->actingAs($user)->patch(route('debts.toggle-active', $debt));
        $this->assertFalse($debt->fresh()->is_active);

        $this->actingAs($user)->patch(route('debts.toggle-active', $debt));
        $this->assertTrue($debt->fresh()->is_active);
    }

    public function test_user_can_amortize_free_debt_and_account_balance_is_deducted(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Empréstimo Familiar',
            'type' => Debt::TYPE_FREE,
            'total_amount' => 2000.00,
            'is_active' => true,
        ]);

        $initialBalance = (float) $account->fresh()->balance; // 5000.00

        $response = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'amount' => '500,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-05',
            'notes' => 'Amortização parcela 1',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));

        // Verifica débito na conta bancária (5000 - 500 = 4500)
        $this->assertEquals(4500.00, (float) $account->fresh()->balance);

        // Verifica criação da transação
        $this->assertDatabaseHas('transactions', [
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'amount' => '500.00',
            'type' => 'expense',
        ]);

        // Verifica parcela gerada e marcada como paga
        $debt->refresh();
        $this->assertCount(1, $debt->installments);
        $this->assertEquals(500.00, $debt->totalPaid());
        $this->assertEquals(1500.00, $debt->remainingBalance());
        $this->assertEquals(25.0, $debt->progressPercentage());
        $this->assertTrue($debt->is_active);

        // Amortiza os 1500 restantes para quitar a dívida
        $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'amount' => '1500.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-10',
        ]);

        $debt->refresh();
        $this->assertEquals(2000.00, $debt->totalPaid());
        $this->assertEquals(0.0, $debt->remainingBalance());
        $this->assertEquals(100.0, $debt->progressPercentage());
        $this->assertFalse($debt->is_active); // Quitou totalmente -> inativa automaticamente!
        $this->assertEquals(3000.00, (float) $account->fresh()->balance);
    }

    public function test_user_can_pay_installment_and_account_balance_is_deducted(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Carnê Sofá',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 600.00,
            'total_installments' => 2,
            'start_date' => '2026-09-10',
            'due_day' => 10,
            'default_account_id' => $account->id,
            'default_category_id' => $category->id,
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installment1 = $debt->installments()->where('installment_number', 1)->first();
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $installment1->status);

        // Paga a parcela 1
        $response = $this->actingAs($user)->post(route('debts.installments.pay', $installment1), [
            'amount' => '300.00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'agenda', 'month' => 9, 'year' => 2026]));

        // Verifica saldo debitado (5000 - 300 = 4700)
        $this->assertEquals(4700.00, (float) $account->fresh()->balance);

        $installment1->refresh();
        $this->assertTrue($installment1->isPaid());
        $this->assertNotNull($installment1->transaction_id);

        $debt->refresh();
        $this->assertEquals(300.00, $debt->totalPaid());
        $this->assertEquals(300.00, $debt->remainingBalance());
        $this->assertEquals(50.0, $debt->progressPercentage());
        $this->assertEquals(1, $debt->paidCount());
        $this->assertTrue($debt->is_active);
    }

    public function test_user_can_unpay_installment_and_account_balance_is_restored(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Carnê Celular',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 500.00,
            'total_installments' => 1,
            'start_date' => '2026-09-10',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installment = $debt->installments()->first();

        // Paga
        $this->actingAs($user)->post(route('debts.installments.pay', $installment), [
            'amount' => '500.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-10',
        ]);

        $this->assertEquals(4500.00, (float) $account->fresh()->balance);
        $installment->refresh();
        $this->assertTrue($installment->isPaid());

        // Desfaz o pagamento
        $responseUnpay = $this->actingAs($user)->post(route('debts.installments.unpay', $installment));
        $responseUnpay->assertRedirect(route('debts.index', ['tab' => 'agenda', 'month' => 9, 'year' => 2026]));

        // Saldo restaurado para 5000
        $this->assertEquals(5000.00, (float) $account->fresh()->balance);

        $installment->refresh();
        $this->assertFalse($installment->isPaid());
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $installment->status);
        $this->assertNull($installment->transaction_id);
    }

    public function test_deleting_transaction_linked_to_installment_resets_installment_to_pending(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Carnê TV',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 1000.00,
            'total_installments' => 1,
            'start_date' => '2026-09-10',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installment = $debt->installments()->first();

        // Paga a parcela
        $this->actingAs($user)->post(route('debts.installments.pay', $installment), [
            'amount' => '1000.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-10',
        ]);

        $installment->refresh();
        $txId = $installment->transaction_id;
        $this->assertNotNull($txId);

        // Exclui o lançamento diretamente na rota de transações
        $this->actingAs($user)->delete(route('transactions.destroy', $txId));

        // Parcela volta a ser pendente e desvincula a transação
        $installment->refresh();
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $installment->status);
        $this->assertNull($installment->transaction_id);
    }

    public function test_dashboard_displays_debt_reminder_chips(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'IPVA 2026',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 600.00,
            'total_installments' => 1,
            'start_date' => Carbon::now()->toDateString(),
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('IPVA 2026');
        $response->assertSee('Boleto / Conta');
    }

    public function test_reversing_or_deleting_free_debt_amortization_deletes_installment_completely(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Empréstimo Irmão',
            'type' => Debt::TYPE_FREE,
            'total_amount' => 1000.00,
            'is_active' => true,
        ]);

        // 1. Faz uma amortização de R$ 300
        $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'amount' => '300.00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-03',
        ]);

        $debt->refresh();
        $this->assertCount(1, $debt->installments);
        $installment = $debt->installments()->first();
        $txId = $installment->transaction_id;
        $this->assertNotNull($txId);
        $this->assertEquals(700.00, $debt->remainingBalance());

        // 2. Exclui o lançamento da conta (simulando estorno/deleção)
        $this->actingAs($user)->delete(route('transactions.destroy', $txId));

        // 3. A parcela de amortização DEVE ter sido excluída, NUNCA mantida como pendente!
        $debt->refresh();
        $this->assertCount(0, $debt->installments);
        $this->assertDatabaseMissing('debt_installments', ['id' => $installment->id]);
        $this->assertEquals(1000.00, $debt->remainingBalance());

        // 4. Na agenda, nenhuma pendência fantasma deve aparecer
        $responseAgenda = $this->actingAs($user)->get(route('debts.index', ['tab' => 'agenda']));
        $responseAgenda->assertDontSee('Amortização avulsa');
        $this->assertEquals(0, DebtInstallment::where('debt_id', $debt->id)->where('status', DebtInstallment::STATUS_PENDING)->count());
    }

    public function test_debts_screen_only_lists_and_accepts_expense_categories(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $categoryExpense] = $this->setupCoupleAndUser();

        // Cria uma categoria de receita
        $categoryIncome = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Salário Mensal',
            'type' => 'income',
            'color' => '#10b981',
        ]);

        $response = $this->actingAs($user)->get(route('debts.index'));
        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) use ($categoryIncome, $categoryExpense) {
            return $categories->contains('id', $categoryExpense->id)
                && ! $categories->contains('id', $categoryIncome->id);
        });

        // Tentar cadastrar dívida com categoria de receita deve falhar na validação
        $responsePost = $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Dívida Inválida',
            'type' => Debt::TYPE_FREE,
            'total_amount' => '100,00',
            'default_category_id' => $categoryIncome->id,
        ]);

        $responsePost->assertSessionHasErrors('default_category_id');
    }

    public function test_cannot_create_installment_debt_where_total_amount_is_less_than_sum_of_installments(): void
    {
        ['user' => $user] = $this->setupCoupleAndUser();

        // 10 parcelas de R$ 200,00 = R$ 2.000,00, mas informando R$ 1.000,00 como total
        $response = $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Financiamento Incoerente',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => '1.000,00',
            'total_installments' => 10,
            'installment_amount' => '200,00',
            'start_date' => '2026-09-15',
        ]);

        $response->assertSessionHasErrors('total_amount');
        $this->assertDatabaseMissing('debts', ['name' => 'Financiamento Incoerente']);
    }

    public function test_current_month_overdue_installment_is_reflected_in_kpi_card(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // Parcela com vencimento ontem
        $yesterday = Carbon::today()->subDay();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Boleto Atrasado Teste',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 1500.00,
            'total_installments' => 1,
            'start_date' => $yesterday->toDateString(),
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $response = $this->actingAs($user)->get(route('debts.index', [
            'tab' => 'agenda',
            'month' => $yesterday->month,
            'year' => $yesterday->year,
        ]));

        $response->assertOk();
        $response->assertViewHas('overdueCount', 1);
        $response->assertViewHas('totalOverdueAmount', 1500.00);
        $response->assertDontSee('Nenhuma atrasada 🎉');
        $response->assertSee('1.500,00');
    }

    public function test_amortize_reducing_term_with_custom_paid_amount_and_residual_installment(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Consórcio Imobiliário',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 10000.00,
            'total_installments' => 10,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $inst8 = $debt->installments()->where('installment_number', 8)->first();
        $inst9 = $debt->installments()->where('installment_number', 9)->first();
        $inst10 = $debt->installments()->where('installment_number', 10)->first();

        // Amortiza quitando as últimas 2 parcelas (#9 e #10) com desconto (R$ 1.700 em vez de R$ 2.000)
        // e ajusta a parcela residual #8 para R$ 800,00
        $response = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'strategy' => 'reduce_term',
            'term_installments_count' => 2,
            'amount' => '1.700,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
            'notes' => 'Amortização com desconto no Santander',
            'residual_installment_id' => $inst8->id,
            'residual_new_amount' => '800,00',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));
        $response->assertSessionHas('success');

        // Verifica transação
        $this->assertDatabaseHas('transactions', [
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'amount' => 1700.00,
            'type' => 'expense',
        ]);

        // #9 e #10 devem estar quitadas
        $inst9->refresh();
        $inst10->refresh();
        $this->assertEquals(DebtInstallment::STATUS_PAID, $inst9->status);
        $this->assertEquals(DebtInstallment::STATUS_PAID, $inst10->status);
        $this->assertEquals(1700.00, (float)$inst9->amount + (float)$inst10->amount);

        // #8 deve continuar pendente com saldo residual de R$ 800
        $inst8->refresh();
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $inst8->status);
        $this->assertEquals(800.00, (float)$inst8->amount);

        // Parcelas pendentes: #1..#7 (7 * 1000 = 7000) + #8 (800) = 7800.00
        $this->assertEquals(7800.00, $debt->pendingInstallmentsSum());
        // Saldo restante contratual: 10.000 - 1.700 = 8.300,00
        $this->assertEquals(8300.00, $debt->remainingBalance());
        $this->assertEquals(1700.00, $debt->totalPaid());
    }

    public function test_amortize_reducing_installment_amount_with_custom_new_amount(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Empréstimo Caixa',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 10000.00,
            'total_installments' => 10,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Aporte extraordinário de R$ 3.000, recalculando as parcelas restantes para R$ 700 cada
        $response = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'strategy' => 'reduce_amount',
            'amount' => '3.000,00',
            'new_installment_amount' => '700,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
            'notes' => 'Aporte para reduzir prestação',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));

        // Verifica transação
        $this->assertDatabaseHas('transactions', [
            'amount' => 3000.00,
            'type' => 'expense',
        ]);

        // Todas as 10 parcelas pendentes agora devem valer R$ 700,00 cada
        $pending = $debt->pendingInstallments()->get();
        $this->assertCount(10, $pending);
        foreach ($pending as $inst) {
            $this->assertEquals(700.00, (float)$inst->amount);
        }

        // Saldo restante: 10 * 700 = 7.000,00
        $this->assertEquals(7000.00, $debt->remainingBalance());
        $this->assertEquals(3000.00, $debt->totalPaid());
    }

    public function test_amortize_reduce_amount_supports_thousands_separator_without_comma(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Teste Amortização 1300',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 15000.00,
            'total_installments' => 10,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Usuário digita '1.500' e define nova parcela como '1.300' (usando ponto de milhar, sem vírgula)
        $response = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'strategy' => 'reduce_amount',
            'amount' => '1.500',
            'new_installment_amount' => '1.300',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
            'notes' => 'Teste com ponto de milhar sem vírgula',
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));

        // Transação deve ser de R$ 1.500,00 e NÃO R$ 1,50
        $this->assertDatabaseHas('transactions', [
            'amount' => 1500.00,
            'type' => 'expense',
        ]);

        // Todas as parcelas pendentes devem ter sido atualizadas para R$ 1.300,00 e NÃO R$ 1,30
        $pending = $debt->pendingInstallments()->get();
        $this->assertCount(10, $pending);
        foreach ($pending as $inst) {
            $this->assertEquals(1300.00, (float) $inst->amount);
        }
    }

    public function test_amortize_selecting_specific_installments_with_custom_paid_values(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Moto',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 2500.00,
            'total_installments' => 5,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $inst2 = $debt->installments()->where('installment_number', 2)->first();
        $inst4 = $debt->installments()->where('installment_number', 4)->first();

        // Quita #2 com desconto (paga 450) e amortiza parcialmente #4 (paga 200, ficando 300)
        // Total desembolsado = 650,00
        $response = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'strategy' => 'select_installments',
            'amount' => '650,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
            'selected_installments' => [
                [
                    'id' => $inst2->id,
                    'paid_amount' => '450,00',
                    'is_fully_paid' => '1',
                ],
                [
                    'id' => $inst4->id,
                    'paid_amount' => '200,00',
                    'is_fully_paid' => '0',
                    'new_remaining_amount' => '300,00',
                ],
            ],
        ]);

        $response->assertRedirect(route('debts.index', ['tab' => 'dividas']));

        // #2 deve estar quitada com valor 450
        $inst2->refresh();
        $this->assertEquals(DebtInstallment::STATUS_PAID, $inst2->status);
        $this->assertEquals(450.00, (float)$inst2->amount);

        // #4 deve continuar pendente com valor ajustado de 300
        $inst4->refresh();
        $this->assertEquals(DebtInstallment::STATUS_PENDING, $inst4->status);
        $this->assertEquals(300.00, (float)$inst4->amount);

        // Parcelas pendentes: #1 (500) + #3 (500) + #4 (300) + #5 (500) = 1800.00
        $this->assertEquals(1800.00, $debt->pendingInstallmentsSum());
        $this->assertEquals(1850.00, $debt->remainingBalance());
        $this->assertEquals(650.00, $debt->totalPaid());
    }

    public function test_agenda_month_filter_strictly_shows_only_selected_month_installments(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento 3 Meses',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-02',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Parcela 1: 2026-09-02 (Setembro)
        // Parcela 2: 2026-10-02 (Outubro)
        // Parcela 3: 2026-11-02 (Novembro)

        // Ao acessar Novembro de 2026, a listagem DEVE conter apenas a parcela de Novembro
        $response = $this->actingAs($user)->get(route('debts.index', [
            'tab' => 'agenda',
            'month' => 11,
            'year' => 2026,
        ]));

        $response->assertOk();
        $displayedItems = $response->viewData('displayedAgendaItems');
        $this->assertCount(1, $displayedItems);
        $this->assertEquals(3, $displayedItems->first()->installment_number);
        $this->assertEquals('2026-11-02', Carbon::parse($displayedItems->first()->due_date)->toDateString());
    }

    public function test_original_amount_and_paid_amount_are_displayed_and_tracked(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Especial',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 1000.00,
            'total_installments' => 2,
            'start_date' => '2026-09-10',
            'due_day' => 10,
            'default_account_id' => $account->id,
            'default_category_id' => $category->id,
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installment1 = $debt->installments()->where('installment_number', 1)->first();
        $this->assertEquals(500.00, (float) $installment1->original_amount);
        $this->assertEquals(500.00, (float) $installment1->amount);
        $this->assertNull($installment1->paid_amount);

        // Paga a parcela 1 com desconto (R$ 450,00)
        $this->actingAs($user)->post(route('debts.installments.pay', $installment1), [
            'amount' => '450,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
        ]);

        $installment1->refresh();
        $this->assertTrue($installment1->isPaid());
        $this->assertEquals(500.00, (float) $installment1->original_amount);
        $this->assertEquals(450.00, (float) $installment1->paid_amount);

        // Visualiza a agenda
        $response = $this->actingAs($user)->get(route('debts.index', [
            'tab' => 'agenda',
            'month' => 9,
            'year' => 2026,
        ]));
        $response->assertOk();
        $response->assertSee('450,00');
        $response->assertSee('500,00');
        $response->assertSee('-R$ 50,00');

        // Desfaz o pagamento
        $this->actingAs($user)->post(route('debts.installments.unpay', $installment1));
        $installment1->refresh();
        $this->assertFalse($installment1->isPaid());
        $this->assertNull($installment1->paid_amount);
        $this->assertEquals(500.00, (float) $installment1->amount);
        $this->assertEquals(500.00, (float) $installment1->original_amount);
    }

    public function test_paying_or_unpaying_installment_stays_on_same_screen_and_reopens_modal_if_requested(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Moto',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 1000.00,
            'total_installments' => 2,
            'start_date' => '2026-09-10',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installment1 = $debt->installments()->where('installment_number', 1)->first();

        // Pagando a partir do modal de cronograma na aba dividas
        $sameScreenUrl = route('debts.index', ['tab' => 'dividas']);
        $response = $this->actingAs($user)->post(route('debts.installments.pay', $installment1), [
            'amount' => '500,00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-10',
            'redirect_to' => $sameScreenUrl,
            'schedule_debt_id' => $debt->id,
        ]);

        $response->assertRedirect($sameScreenUrl);
        $response->assertSessionHas('open_schedule_debt_id', $debt->id);
        $this->assertTrue($installment1->fresh()->isPaid());

        // Desfazendo o pagamento mantendo a tela
        $responseUnpay = $this->actingAs($user)->post(route('debts.installments.unpay', $installment1), [
            'redirect_to' => $sameScreenUrl,
            'schedule_debt_id' => $debt->id,
        ]);

        $responseUnpay->assertRedirect($sameScreenUrl);
        $responseUnpay->assertSessionHas('open_schedule_debt_id', $debt->id);
        $this->assertFalse($installment1->fresh()->isPaid());
    }

    public function test_unpaying_extraordinary_amortization_restores_pending_installments_to_original_amount_and_deletes_extra_installment(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Consórcio Carro',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 5000.00,
            'total_installments' => 5,
            'start_date' => '2026-09-10',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Amortiza R$ 1.000 reduzindo o valor das parcelas para R$ 800 cada
        $resAmortize = $this->actingAs($user)->post(route('debts.amortize', $debt), [
            'strategy' => 'reduce_amount',
            'amount' => '1.000,00',
            'new_installment_amount' => '800,00',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-10',
        ]);
        $resAmortize->assertSessionHasNoErrors();

        $extraInst = $debt->installments()->where('installment_number', '>', 5)->first();
        $this->assertNotNull($extraInst);
        $this->assertTrue($extraInst->isExtraordinaryAmortization());

        // As parcelas pendentes agora valem 800
        foreach ($debt->pendingInstallments()->get() as $p) {
            $this->assertEquals(800.00, (float)$p->amount);
            $this->assertEquals(1000.00, (float)$p->original_amount);
        }

        // Estorna/desfaz a amortização extraordinária
        $response = $this->actingAs($user)->post(route('debts.installments.unpay', $extraInst));
        $response->assertSessionHas('success');

        // A parcela de amortização extraordinária deve ter sido excluída
        $this->assertNull(DebtInstallment::find($extraInst->id));

        // Todas as parcelas pendentes devem ter retornado ao valor original (1000.00)
        $pending = $debt->pendingInstallments()->get();
        $this->assertCount(5, $pending);
        foreach ($pending as $p) {
            $this->assertEquals(1000.00, (float)$p->amount);
            $this->assertEquals(1000.00, (float)$p->original_amount);
        }
    }

    public function test_user_can_reset_single_installment_and_all_installments_to_original_amount(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Empréstimo Pessoal',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-10',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Simula redução de valor em duas parcelas pendentes
        $inst1 = $debt->installments()->where('installment_number', 1)->first();
        $inst2 = $debt->installments()->where('installment_number', 2)->first();
        $inst1->update(['amount' => 600.00]);
        $inst2->update(['amount' => 600.00]);

        // 1. Reset de uma parcela individual
        $response1 = $this->actingAs($user)->patch(route('debts.installments.reset-amount', $inst1), [
            'redirect_to' => route('debts.index', ['tab' => 'agenda']),
            'schedule_debt_id' => $debt->id,
        ]);
        $response1->assertRedirect(route('debts.index', ['tab' => 'agenda']));
        $this->assertEquals(1000.00, (float)$inst1->fresh()->amount);
        $this->assertEquals(600.00, (float)$inst2->fresh()->amount); // Ainda 600

        // 2. Reset de todas as parcelas pendentes da dívida
        $responseAll = $this->actingAs($user)->post(route('debts.reset-all-installments', $debt), [
            'redirect_to' => route('debts.index', ['tab' => 'agenda']),
            'schedule_debt_id' => $debt->id,
        ]);
        $responseAll->assertRedirect(route('debts.index', ['tab' => 'agenda']));
        $this->assertEquals(1000.00, (float)$inst2->fresh()->amount);
    }

    public function test_user_can_create_debt_where_installments_sum_is_less_than_total_amount(): void
    {
        ['user' => $user] = $this->setupCoupleAndUser();

        // Total 5.000,00 com 5 parcelas de 600,00 (soma = 3.000,00 < 5.000,00)
        $response = $this->actingAs($user)->post(route('debts.store'), [
            'name' => 'Financiamento Flexível',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => '5.000,00',
            'total_installments' => 5,
            'installment_amount' => '600,00',
            'start_date' => '2026-09-15',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('debts.index', ['tab' => 'agenda']));

        $debt = Debt::where('name', 'Financiamento Flexível')->first();
        $this->assertNotNull($debt);
        $this->assertEquals(5000.00, (float) $debt->total_amount);
        $this->assertCount(5, $debt->installments);
        $this->assertEquals(3000.00, $debt->pendingInstallmentsSum());
        $this->assertEquals(5000.00, $debt->remainingBalance());
    }

    public function test_user_can_update_debt_total_amount_respecting_existing_installments_ceiling(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Empréstimo Flex',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 4000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments(); // 3 parcelas de ~1333.33 somando 4000

        // 1. Aumentar o total da dívida para 6.000 é permitido
        $responseUp = $this->actingAs($user)->put(route('debts.update', $debt), [
            'name' => 'Empréstimo Flex',
            'total_amount' => '6.000,00',
        ]);
        $responseUp->assertSessionHasNoErrors();
        $this->assertEquals(6000.00, (float) $debt->fresh()->total_amount);
        $this->assertEquals(6000.00, $debt->fresh()->remainingBalance());

        // 2. Reduzir o total para 2.000 (quando as parcelas pendentes somam 4000) DEVE falhar
        $responseDown = $this->actingAs($user)->put(route('debts.update', $debt), [
            'name' => 'Empréstimo Flex',
            'total_amount' => '2.000,00',
        ]);
        $responseDown->assertSessionHasErrors('total_amount');
        $this->assertEquals(6000.00, (float) $debt->fresh()->total_amount);
    }

    public function test_user_can_adjust_remaining_installments_updating_debt_total_amount(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account, 'category' => $category] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Reforma',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Paga a primeira parcela (1.000)
        $inst1 = $debt->installments()->where('installment_number', 1)->first();
        $this->actingAs($user)->post(route('debts.installments.pay', $inst1), [
            'amount' => '1000.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-15',
        ]);

        // Ajusta as 2 parcelas restantes para R$ 1.500 cada, marcando para atualizar o total
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1.500,00',
            'update_total_amount' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $debt->refresh();
        // Total da dívida agora é: 1.000 (já pago) + (2 * 1.500) = 4.000,00
        $this->assertEquals(4000.00, (float) $debt->total_amount);
        $this->assertEquals(1000.00, $debt->totalPaid());
        $this->assertEquals(3000.00, $debt->remainingBalance());

        $pending = $debt->pendingInstallments()->get();
        $this->assertCount(2, $pending);
        foreach ($pending as $p) {
            $this->assertEquals(1500.00, (float) $p->amount);
        }
    }

    public function test_user_can_adjust_remaining_installments_keeping_debt_total_amount(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Curso',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Paga a primeira parcela (1.000)
        $inst1 = $debt->installments()->where('installment_number', 1)->first();
        $this->actingAs($user)->post(route('debts.installments.pay', $inst1), [
            'amount' => '1000.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-15',
        ]);

        // Ajusta as 2 restantes para R$ 800 cada, mantendo o total da dívida
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '800,00',
            'update_total_amount' => '0',
        ]);

        $response->assertSessionHasNoErrors();

        $debt->refresh();
        // Total da dívida permanece 3.000,00
        $this->assertEquals(3000.00, (float) $debt->total_amount);
        $this->assertEquals(1000.00, $debt->totalPaid());
        $this->assertEquals(2000.00, $debt->remainingBalance());

        $pending = $debt->pendingInstallments()->get();
        $this->assertCount(2, $pending);
        foreach ($pending as $p) {
            $this->assertEquals(800.00, (float) $p->amount);
        }
        $this->assertEquals(1600.00, $debt->pendingInstallmentsSum());
    }

    public function test_adjusting_remaining_installments_exceeding_balance_fails_if_not_updating_total_and_no_reduction(): void
    {
        ['user' => $user, 'couple' => $couple, 'account' => $account] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Viagem',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Paga a primeira parcela (1.000). Saldo devedor restante = 2.000
        $inst1 = $debt->installments()->where('installment_number', 1)->first();
        $this->actingAs($user)->post(route('debts.installments.pay', $inst1), [
            'amount' => '1000.00',
            'account_id' => $account->id,
            'paid_at' => '2026-09-15',
        ]);

        // Tenta colocar 2 parcelas de R$ 1.200 (soma 2.400 > 2.000) mantendo o total
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1.200,00',
            'update_total_amount' => '0',
        ]);

        $response->assertSessionHasErrors('new_installment_amount');
    }

    public function test_adjusting_remaining_installments_with_reduction_reduces_count_and_adjusts_residual(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // Dívida com saldo de 1.000 e 5 parcelas de 200
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Aparelho',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 1000.00,
            'total_installments' => 5,
            'start_date' => '2026-09-15',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Usuário quer alterar para parcelas de R$ 300, aceitando reduzir para 4 parcelas
        // (3 de R$ 300 + 1 de R$ 100 = R$ 1.000)
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '300,00',
            'update_total_amount' => '0',
            'reduce_installments_count' => 4,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(1000.00, (float) $debt->total_amount);
        $this->assertEquals(4, (int) $debt->total_installments);

        $pending = $debt->pendingInstallments()->orderBy('installment_number')->get();
        $this->assertCount(4, $pending);

        // Primeiras 3 de 300
        $this->assertEquals(300.00, (float) $pending[0]->amount);
        $this->assertEquals(300.00, (float) $pending[1]->amount);
        $this->assertEquals(300.00, (float) $pending[2]->amount);
        // Última parcela com o residual de 100
        $this->assertEquals(100.00, (float) $pending[3]->amount);

        // Soma total das parcelas cravada em 1.000
        $this->assertEquals(1000.00, $debt->pendingInstallmentsSum());
        $this->assertEquals(1000.00, $debt->remainingBalance());
    }

    public function test_user_can_adjust_due_date_and_due_day_of_remaining_installments(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Financiamento Mudança de Data',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'start_date' => '2026-09-10',
            'due_day' => 10,
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Altera para dia 25 e primeira parcela para 2026-11-25
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1000,00',
            'due_day' => 25,
            'first_due_date' => '2026-11-25',
            'update_total_amount' => '0',
        ]);

        $response->assertSessionHasNoErrors();

        $debt->refresh();
        $this->assertEquals(25, $debt->due_day);

        $pending = $debt->pendingInstallments()->orderBy('installment_number')->get();
        $this->assertCount(3, $pending);
        $this->assertEquals('2026-11-25', $pending[0]->due_date->toDateString());
        $this->assertEquals('2026-12-25', $pending[1]->due_date->toDateString());
        $this->assertEquals('2027-01-25', $pending[2]->due_date->toDateString());
    }

    public function test_adjust_remaining_installments_reducing_count_with_residual_last_installment(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // 10 parcelas de 1.500 = 15.000
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida 15k Teste',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 15000.00,
            'total_installments' => 10,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Aumenta valor da parcela para 1.600 e reduz para 9 parcelas
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1600,00',
            'update_total_amount' => '0',
            'reduce_installments_count' => '9',
            'adjust_last_installment' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $debt->refresh();
        $this->assertEquals(9, $debt->total_installments);
        $this->assertEquals(15000.00, $debt->remainingBalance());

        $pending = $debt->pendingInstallments()->orderBy('installment_number')->get();
        $this->assertCount(9, $pending);

        // Primeiras 8 de 1.600 = 12.800
        for ($i = 0; $i < 8; $i++) {
            $this->assertEquals(1600.00, (float) $pending[$i]->amount);
        }

        // Última parcela com 2.200 (15.000 - 12.800)
        $this->assertEquals(2200.00, (float) $pending[8]->amount);
        $this->assertEquals(15000.00, $debt->pendingInstallmentsSum());
    }

    public function test_adjust_remaining_installments_keeping_count_with_residual_last_installment(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // 10 parcelas de 1.500 = 15.000
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida 15k Manter Parcelas',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 15000.00,
            'total_installments' => 10,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Aumenta valor da parcela para 1.600, mantém 10 parcelas e ajusta última para cravar
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1600,00',
            'update_total_amount' => '0',
            'reduce_installments_count' => '10',
            'adjust_last_installment' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $debt->refresh();
        $this->assertEquals(10, $debt->total_installments);
        $this->assertEquals(15000.00, $debt->remainingBalance());

        $pending = $debt->pendingInstallments()->orderBy('installment_number')->get();
        $this->assertCount(10, $pending);

        // Primeiras 9 de 1.600 = 14.400
        for ($i = 0; $i < 9; $i++) {
            $this->assertEquals(1600.00, (float) $pending[$i]->amount);
        }

        // Última parcela com 600 (15.000 - 14.400)
        $this->assertEquals(600.00, (float) $pending[9]->amount);
        $this->assertEquals(15000.00, $debt->pendingInstallmentsSum());
    }

    public function test_adjust_remaining_installments_reducing_count_with_sum_less_than_total_does_not_inflate_last_installment(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // Dívida de 20.000 com 10 parcelas de 1.500 = 15.000 (menor que o total)
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida 20k Teste',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 20000.00,
            'total_installments' => 10,
            'installment_amount' => 1500.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Usuário reduz para 9 parcelas de 1.000 sem solicitar ajuste residual da última
        $response = $this->actingAs($user)->post(route('debts.adjust-remaining-installments', $debt), [
            'new_installment_amount' => '1000,00',
            'update_total_amount' => '0',
            'reduce_installments_count' => '9',
            'adjust_last_installment' => '0',
        ]);

        $response->assertSessionHasNoErrors();

        $debt->refresh();
        $this->assertEquals(9, $debt->total_installments);
        $this->assertEquals(20000.00, (float) $debt->total_amount);

        $pending = $debt->pendingInstallments()->orderBy('installment_number')->get();
        $this->assertCount(9, $pending);

        // Todas as 9 parcelas devem ser exatamente 1.000,00 (sem inflar a última para 12.000)
        foreach ($pending as $inst) {
            $this->assertEquals(1000.00, (float) $inst->amount);
        }

        $this->assertEquals(9000.00, $debt->pendingInstallmentsSum());
    }

    public function test_user_can_add_single_installment_to_debt(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Adicionar Parcela',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 5000.00,
            'total_installments' => 2,
            'installment_amount' => 1000.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $this->assertEquals(2, $debt->total_installments);

        // Adiciona 3ª parcela de 1.000 para 2026-11-01
        $response = $this->actingAs($user)->post(route('debts.installments.store', $debt), [
            'amount' => '1000,00',
            'due_date' => '2026-11-01',
            'notes' => 'Parcela adicional avulsa',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(3, $debt->total_installments);
        $this->assertEquals(5000.00, (float) $debt->total_amount);

        $installments = $debt->installments()->orderBy('installment_number')->get();
        $this->assertCount(3, $installments);
        $this->assertEquals('2026-11-01', $installments[2]->due_date->toDateString());
        $this->assertEquals(1000.00, (float) $installments[2]->amount);
        $this->assertEquals('Parcela adicional avulsa', $installments[2]->notes);
    }

    public function test_cannot_add_installment_exceeding_total_debt_unless_flagged(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        // 2 parcelas de 1.000 = 2.000, total = 2.500
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Teto Adicionar',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 2500.00,
            'total_installments' => 2,
            'installment_amount' => 1000.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        // Tenta adicionar parcela de 800 (2.000 + 800 = 2.800 > 2.500) sem flag
        $response = $this->actingAs($user)->post(route('debts.installments.store', $debt), [
            'amount' => '800,00',
            'due_date' => '2026-11-01',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertEquals(2, $debt->fresh()->total_installments);

        // Agora adiciona marcando update_total_amount = 1
        $responseOk = $this->actingAs($user)->post(route('debts.installments.store', $debt), [
            'amount' => '800,00',
            'due_date' => '2026-11-01',
            'update_total_amount' => '1',
        ]);

        $responseOk->assertSessionHasNoErrors();
        $debt->refresh();
        $this->assertEquals(3, $debt->total_installments);
        $this->assertEquals(2800.00, (float) $debt->total_amount);
    }

    public function test_user_can_edit_single_pending_installment(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Editar Parcela',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 5000.00,
            'total_installments' => 2,
            'installment_amount' => 1000.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $firstInst = $debt->installments()->orderBy('installment_number')->first();

        // Edita valor para 1.200 e vencimento para dia 15
        $response = $this->actingAs($user)->patch(route('debts.installments.update', $firstInst), [
            'amount' => '1200,00',
            'due_date' => '2026-09-15',
            'notes' => 'Ajuste pontual',
        ]);

        $response->assertSessionHasNoErrors();
        $firstInst->refresh();
        $this->assertEquals(1200.00, (float) $firstInst->amount);
        $this->assertEquals('2026-09-15', $firstInst->due_date->toDateString());
        $this->assertEquals('Ajuste pontual', $firstInst->notes);
    }

    public function test_user_can_delete_single_pending_installment_with_renumbering(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Deletar Parcela',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 3000.00,
            'total_installments' => 3,
            'installment_amount' => 1000.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $installments = $debt->installments()->orderBy('installment_number')->get();
        $this->assertCount(3, $installments);

        // Exclui a parcela intermediária (#2)
        $secondInst = $installments[1];
        $response = $this->actingAs($user)->delete(route('debts.installments.destroy', $secondInst));

        $response->assertSessionHasNoErrors();
        $debt->refresh();
        $this->assertEquals(2, $debt->total_installments);

        $remaining = $debt->installments()->orderBy('installment_number')->get();
        $this->assertCount(2, $remaining);

        // A antiga parcela #3 agora deve ser numerada como #2
        $this->assertEquals(1, $remaining[0]->installment_number);
        $this->assertEquals(2, $remaining[1]->installment_number);
    }

    public function test_cannot_delete_paid_installment_directly(): void
    {
        ['user' => $user, 'couple' => $couple] = $this->setupCoupleAndUser();

        $debt = Debt::create([
            'couple_id' => $couple->id,
            'name' => 'Dívida Não Deletar Paga',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => 2000.00,
            'total_installments' => 2,
            'installment_amount' => 1000.00,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
        $debt->generateScheduledInstallments();

        $firstInst = $debt->installments()->first();
        $firstInst->update(['status' => DebtInstallment::STATUS_PAID]);

        $response = $this->actingAs($user)->delete(route('debts.installments.destroy', $firstInst));
        $response->assertStatus(400);

        $this->assertDatabaseHas('debt_installments', ['id' => $firstInst->id]);
    }
}
