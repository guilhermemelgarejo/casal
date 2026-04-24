<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_calculates_main_kpis_with_consistent_rules(): void
    {
        $couple = Couple::factory()->create(['monthly_income' => 2000]);
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $regular = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta Corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#222222',
        ]);
        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#111111',
            'credit_card_limit_total' => '1000.00',
        ]);

        $catFood = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Alimentação',
            'type' => 'expense',
            'color' => '#aaaaaa',
        ]);
        $catInvoice = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
            'color' => '#bbbbbb',
        ]);

        Budget::create([
            'couple_id' => $couple->id,
            'category_id' => $catFood->id,
            'amount' => '800.00',
            'month' => 6,
            'year' => 2026,
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $regular->id,
            'description' => 'Salário',
            'amount' => '2000.00',
            'payment_method' => 'Pix',
            'type' => 'income',
            'date' => Carbon::createFromDate(2026, 6, 5)->toDateString(),
            'reference_month' => 6,
            'reference_year' => 2026,
        ], [['category_id' => $catFood->id, 'amount' => '2000.00']]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $regular->id,
            'description' => 'Mercado',
            'amount' => '500.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => Carbon::createFromDate(2026, 6, 6)->toDateString(),
            'reference_month' => 6,
            'reference_year' => 2026,
        ], [['category_id' => $catFood->id, 'amount' => '500.00']]);

        $statement = CreditCardStatement::create([
            'couple_id' => $couple->id,
            'account_id' => $card->id,
            'reference_month' => 5,
            'reference_year' => 2026,
            'spent_total' => '300.00',
            'paid_at' => null,
        ]);

        $paymentTx = $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $regular->id,
            'description' => 'Pagamento fatura Visa (05/2026)',
            'amount' => '300.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => Carbon::createFromDate(2026, 6, 10)->toDateString(),
            'reference_month' => 6,
            'reference_year' => 2026,
        ], [['category_id' => $catInvoice->id, 'amount' => '300.00']]);
        $statement->paymentTransactions()->attach($paymentTx->id);
        $statement->syncPaidMetadata();

        $response = $this->actingAs($user)->get(route('reports.index', ['period' => '2026-06']));
        $response->assertOk();

        $response->assertViewHas('executiveKpis', function (array $kpis) {
            return (float) $kpis['total_income'] === 2000.0
                && (float) $kpis['total_expense'] === 800.0
                && (float) $kpis['net_result'] === 1200.0
                && (float) $kpis['planned_income'] === 2000.0
                && (float) $kpis['spending_pressure_pct'] === 40.0
                && (float) $kpis['budget_commitment_pct'] === 40.0;
        });

        $response->assertViewHas('budgetSpentTotal', 500.0);
    }
}
