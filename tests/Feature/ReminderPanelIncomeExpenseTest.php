<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderPanelIncomeExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_panel_differentiates_income_and_expense(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#10B981',
        ]);

        $catIncome = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Salário',
            'type' => 'income',
            'color' => '#10B981',
        ]);

        $catExpense = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Moradia',
            'type' => 'expense',
            'color' => '#EF4444',
        ]);

        // 1. Receita Recorrente
        $rtIncome = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Salário Mensal',
            'amount' => '6000.00',
            'type' => 'income',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ]);
        $rtIncome->syncCategorySplits([
            ['category_id' => $catIncome->id, 'amount' => '6000.00'],
        ]);

        // 2. Despesa Recorrente
        $rtExpense = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel do Apartamento',
            'amount' => '2500.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 10,
            'is_active' => true,
        ]);
        $rtExpense->syncCategorySplits([
            ['category_id' => $catExpense->id, 'amount' => '2500.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 1, 10, 0, 0, config('app.timezone')));

        try {
            $response = $this->actingAs($user)->get(route('dashboard', ['period' => '2026-04']));
            $response->assertOk();

            // Valida cabeçalho com separação de contadores a pagar e a receber
            $response->assertSee('1 a pagar', false);
            $response->assertSee('1 a receber', false);

            // Valida botões de filtro por tipo (Despesas / Receitas)
            $response->assertSee('data-filter="expense"', false);
            $response->assertSee('data-filter="income"', false);

            // Valida chips com estilização e badges diferenciados
            $response->assertSee('dz-reminder-chip--income', false);
            $response->assertSee('dz-reminder-chip--expense', false);
            $response->assertSee('data-kind="income"', false);
            $response->assertSee('data-kind="expense"', false);

            // Valida labels específicas
            $response->assertSee('Receita • Mensal', false);
            $response->assertSee('Despesa • Mensal', false);
            $response->assertSee('+ Lançar Receita', false);
            $response->assertSee('+ Lançar Despesa', false);

            // Valida formatação dos valores com + e -
            $response->assertSee('+ R$ 6.000,00', false);
            $response->assertSee('- R$ 2.500,00', false);
        } finally {
            $this->travelBack();
        }
    }
}
