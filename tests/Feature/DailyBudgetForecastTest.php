<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Debt;
use App\Models\DebtInstallment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DailyBudgetForecast;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyBudgetForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_budget_forecast_calculates_correctly_with_card_recurring_and_debts(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 6000.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $regular = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#10B981',
        ]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Nubank',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#8B5CF6',
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Geral',
            'type' => 'expense',
            'color' => '#6B7280',
        ]);

        // 1. Fatura de Cartão para o próximo mês (referência 10/2026)
        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => '1000.00',
            'date' => '2026-09-15',
            'reference_month' => 10,
            'reference_year' => 2026,
            'description' => 'Compra parcelada',
        ]);

        // 2. Despesa Recorrente ativa
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $regular->id,
            'description' => 'Internet Fibra',
            'amount' => '500.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'day_of_month' => 10,
            'is_active' => true,
        ]);

        // 3. Parcela de Dívida com vencimento no próximo mês
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'name' => 'Empréstimo Carro',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => '7000.00',
            'installment_amount' => '700.00',
            'total_installments' => 10,
            'is_active' => true,
        ]);

        DebtInstallment::create([
            'couple_id' => $couple->id,
            'debt_id' => $debt->id,
            'installment_number' => 1,
            'due_date' => '2026-10-15',
            'original_amount' => '700.00',
            'amount' => '700.00',
            'status' => DebtInstallment::STATUS_PENDING,
        ]);

        // Data atual: 09/09/2026 (Setembro tem 30 dias. Dias restantes contando hoje: 30 - 9 + 1 = 22 dias)
        $simulatedNow = Carbon::create(2026, 9, 9, 12, 0, 0);

        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, $simulatedNow);

        // Verificações dos dias
        $this->assertSame(22, $forecast['days_remaining_current_month']);
        $this->assertSame(10, $forecast['target_month']);
        $this->assertSame(2026, $forecast['target_year']);

        // Verificações financeiras
        $this->assertEquals(6000.00, $forecast['planned_income']);
        $this->assertEquals(1000.00, $forecast['card_invoices_total']);
        $this->assertEquals(500.00, $forecast['recurring_expenses_total']);
        $this->assertEquals(700.00, $forecast['debt_installments_total']);

        // Total contratado = 1000 + 500 + 700 = 2200.00
        $this->assertEquals(2200.00, $forecast['committed_total']);

        // Saldo livre = 6000 - 2200 = 3800.00
        $this->assertEquals(3800.00, $forecast['free_amount']);

        // Orçamento diário = 3800 / 22 = 172.73
        $this->assertEquals(round(3800 / 22, 2), $forecast['daily_budget']);
        $this->assertSame('healthy', $forecast['status']);
    }

    public function test_recurring_on_credit_card_already_generated_is_not_duplicated(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 5000.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Mastercard',
            'kind' => Account::KIND_CREDIT_CARD,
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Assinaturas',
            'type' => 'expense',
        ]);

        $rec = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'description' => 'Streaming',
            'amount' => '60.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_CREDIT_CARD,
            'day_of_month' => 5,
            'is_active' => true,
        ]);

        // Já gerou transação no cartão para 10/2026
        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'category_id' => $cat->id,
            'recurring_transaction_id' => $rec->id,
            'type' => 'expense',
            'amount' => '60.00',
            'date' => '2026-10-05',
            'reference_month' => 10,
            'reference_year' => 2026,
            'description' => 'Streaming',
        ]);

        $simulatedNow = Carbon::create(2026, 9, 10, 10, 0, 0);
        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, $simulatedNow);

        // A fatura já soma 60.00; o recorrente não deve duplicar
        $this->assertEquals(60.00, $forecast['card_invoices_total']);
        $this->assertEquals(0.00, $forecast['recurring_expenses_total']);
        $this->assertEquals(60.00, $forecast['committed_total']);
    }

    public function test_forecast_handles_deficit_gracefully(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 1000.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão XP',
            'kind' => Account::KIND_CREDIT_CARD,
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Compras',
            'type' => 'expense',
        ]);

        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => '1500.00',
            'date' => '2026-09-01',
            'reference_month' => 10,
            'reference_year' => 2026,
            'description' => 'Notebook parcelado',
        ]);

        $simulatedNow = Carbon::create(2026, 9, 20, 10, 0, 0);
        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, $simulatedNow);

        $this->assertEquals(1000.00, $forecast['planned_income']);
        $this->assertEquals(1500.00, $forecast['committed_total']);
        $this->assertEquals(-500.00, $forecast['free_amount']);
        $this->assertEquals(0.00, $forecast['daily_budget']);
        $this->assertSame('deficit', $forecast['status']);
    }

    public function test_dashboard_renders_daily_budget_card(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 4500.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('Orçamento Diário');
        $response->assertSee('Receita Prevista');
        $response->assertSee('Faturas Cartão');
        $response->assertSee('Recorrentes');
        $response->assertSee('Dívidas');
        $response->assertSee('dz-forecast-card');
    }

    public function test_recurring_income_is_included_in_planned_income_and_forecast(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 4000.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
        ]);

        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Aluguel Recebido',
            'amount' => '1500.00',
            'type' => 'income',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'day_of_month' => 10,
            'is_active' => true,
        ]);

        $simulatedNow = Carbon::create(2026, 9, 9, 10, 0, 0);
        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, $simulatedNow);

        // 4000 (base) + 1500 (recorrente) = 5500
        $this->assertEquals(4000.00, $forecast['base_planned_income']);
        $this->assertEquals(1500.00, $forecast['recurring_incomes_total']);
        $this->assertEquals(1, $forecast['recurring_incomes_count']);
        $this->assertEquals(5500.00, $forecast['planned_income']);
    }

    public function test_multiple_recurring_transactions_are_excluded_from_forecast(): void
    {
        $couple = Couple::factory()->create([
            'monthly_income' => 3000.00,
        ]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
        ]);

        // Despesa recorrente mensal normal (deve entrar)
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Academia',
            'amount' => '100.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'day_of_month' => 5,
            'is_multiple' => false,
            'is_active' => true,
        ]);

        // Despesa recorrente múltipla / atalho variável (NÃO deve entrar)
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Almoço Trabalho',
            'amount' => '40.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_multiple' => true,
            'is_active' => true,
        ]);

        // Receita recorrente múltipla (NÃO deve entrar)
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Venda Esporádica',
            'amount' => '200.00',
            'type' => 'income',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_multiple' => true,
            'is_active' => true,
        ]);

        $simulatedNow = Carbon::create(2026, 9, 9, 10, 0, 0);
        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, $simulatedNow);

        // Apenas a mensal de 100.00 entra em despesas
        $this->assertEquals(100.00, $forecast['recurring_expenses_total']);
        $this->assertEquals(1, $forecast['recurring_expenses_count']);

        // Receita múltipla não entra (fica apenas a renda base de 3000.00)
        $this->assertEquals(0.00, $forecast['recurring_incomes_total']);
        $this->assertEquals(3000.00, $forecast['planned_income']);
    }

    public function test_forecast_items_are_sorted_by_amount_descending(): void
    {
        $couple = Couple::factory()->create(['monthly_income' => 5000.00]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
        ]);

        $card1 = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão Menor',
            'kind' => Account::KIND_CREDIT_CARD,
        ]);

        $card2 = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão Maior',
            'kind' => Account::KIND_CREDIT_CARD,
        ]);

        $cat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Geral',
            'type' => 'expense',
        ]);

        // Cartão Menor: 200, Cartão Maior: 800
        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card1->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => '200.00',
            'date' => '2026-09-01',
            'reference_month' => 10,
            'reference_year' => 2026,
            'description' => 'Gasto menor',
        ]);
        Transaction::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $card2->id,
            'category_id' => $cat->id,
            'type' => 'expense',
            'amount' => '800.00',
            'date' => '2026-09-01',
            'reference_month' => 10,
            'reference_year' => 2026,
            'description' => 'Gasto maior',
        ]);

        // Recorrentes: 50.00 e 350.00
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Assinatura Pequena',
            'amount' => '50.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_active' => true,
        ]);
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Assinatura Grande',
            'amount' => '350.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_active' => true,
        ]);

        // Dívidas: 150.00 e 900.00
        $debt = Debt::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'name' => 'Dívidas',
            'type' => Debt::TYPE_INSTALLMENTS,
            'total_amount' => '1050.00',
            'installment_amount' => '150.00',
            'total_installments' => 2,
            'is_active' => true,
        ]);
        DebtInstallment::create([
            'couple_id' => $couple->id,
            'debt_id' => $debt->id,
            'installment_number' => 1,
            'due_date' => '2026-10-10',
            'original_amount' => '150.00',
            'amount' => '150.00',
            'status' => DebtInstallment::STATUS_PENDING,
        ]);
        DebtInstallment::create([
            'couple_id' => $couple->id,
            'debt_id' => $debt->id,
            'installment_number' => 2,
            'due_date' => '2026-10-20',
            'original_amount' => '900.00',
            'amount' => '900.00',
            'status' => DebtInstallment::STATUS_PENDING,
        ]);

        // Receitas recorrentes: 300.00 e 1200.00
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Rendimento Menor',
            'amount' => '300.00',
            'type' => 'income',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_active' => true,
        ]);
        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'account_id' => $account->id,
            'description' => 'Rendimento Maior',
            'amount' => '1200.00',
            'type' => 'income',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'is_active' => true,
        ]);

        $forecast = DailyBudgetForecast::calculateForNextMonth($couple, 2026, 9, Carbon::create(2026, 9, 9));

        // Verificando ordenação decrescente de faturas
        $this->assertEquals(800.00, $forecast['card_invoices_items'][0]['amount']);
        $this->assertEquals(200.00, $forecast['card_invoices_items'][1]['amount']);

        // Verificando ordenação decrescente de despesas recorrentes
        $this->assertEquals(350.00, $forecast['recurring_expenses_items'][0]['amount']);
        $this->assertEquals(50.00, $forecast['recurring_expenses_items'][1]['amount']);

        // Verificando ordenação decrescente de dívidas
        $this->assertEquals(900.00, $forecast['debt_installments_items'][0]['amount']);
        $this->assertEquals(150.00, $forecast['debt_installments_items'][1]['amount']);

        // Verificando ordenação decrescente de receitas recorrentes
        $this->assertEquals(1200.00, $forecast['recurring_incomes_items'][0]['amount']);
        $this->assertEquals(300.00, $forecast['recurring_incomes_items'][1]['amount']);
    }
}
