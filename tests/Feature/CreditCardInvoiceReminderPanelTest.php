<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CreditCardStatement;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardInvoiceReminderPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{couple: Couple, user: User, card: Account, checking: Account, category: Category, invoiceCategory: Category}
     */
    private function seedCoupleWithCard(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Visa Lembrete',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#000',
            'credit_card_invoice_due_day' => 10,
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

    public function test_painel_mostra_lembrete_de_fatura_em_aberto_com_link_para_faturas(): void
    {
        extract($this->seedCoupleWithCard());

        $this->travelTo(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra cartão',
                'amount' => '200.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-03-12',
                'reference_month' => 3,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '200.00']]);

            $html = $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertSee('Faturas em aberto', false)
                ->assertSee('Visa Lembrete', false)
                ->assertSee('Ver fatura', false)
                ->assertSee('Vencida', false)
                ->assertSee('rt-reminder-card--overdue', false)
                ->getContent();

            $this->assertStringContainsString('statement-cycle-'.$card->id.'-2026-3', $html);

            $this->actingAs($user)
                ->get(route('transactions.index', ['month' => 4, 'year' => 2026]))
                ->assertOk()
                ->assertSee('Faturas em aberto', false)
                ->assertSee('Visa Lembrete', false);

            $this->actingAs($user)
                ->get(route('recurring-transactions.index'))
                ->assertOk()
                ->assertDontSee('Faturas em aberto', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_painel_usa_duas_colunas_com_recorrente_e_fatura(): void
    {
        extract($this->seedCoupleWithCard());

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel teste colunas',
            'amount' => '99.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $checking->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '99.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra cartão',
                'amount' => '50.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-03-10',
                'reference_month' => 3,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '50.00']]);

            $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertSee('Lembretes', false)
                ->assertSee('rt-reminder-columns--split', false)
                ->assertSee('Recorrentes', false)
                ->assertSee('Faturas de cartão', false)
                ->assertSee('Aluguel teste colunas', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_nao_mostra_fatura_cuja_referencia_e_alem_do_proximo_mes_civil(): void
    {
        extract($this->seedCoupleWithCard());

        $this->travelTo(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra futura',
                'amount' => '50.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-04-20',
                'reference_month' => 6,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '50.00']]);

            $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertDontSee('rt-reminder-strip', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_fatura_nao_vencida_nao_usa_estilo_vermelho_no_painel(): void
    {
        extract($this->seedCoupleWithCard());

        $this->travelTo(Carbon::create(2026, 4, 5, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra',
                'amount' => '30.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-04-04',
                'reference_month' => 4,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '30.00']]);

            $stmt = CreditCardStatement::query()
                ->where('account_id', $card->id)
                ->where('reference_month', 4)
                ->where('reference_year', 2026)
                ->firstOrFail();
            $stmt->update(['due_date' => '2026-04-20']);

            $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertSee('Faturas em aberto', false)
                ->assertDontSee('rt-reminder-card--overdue', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_mostra_fatura_do_proximo_mes_civil(): void
    {
        extract($this->seedCoupleWithCard());

        $this->travelTo(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra próxima fatura',
                'amount' => '40.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-04-18',
                'reference_month' => 5,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '40.00']]);

            $html = $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertSee('Faturas em aberto', false)
                ->assertSee('05/2026', false)
                ->getContent();

            $this->assertStringContainsString('statement-cycle-'.$card->id.'-2026-5', $html);
        } finally {
            $this->travelBack();
        }
    }

    public function test_lembrete_some_quando_fatura_quitada(): void
    {
        extract($this->seedCoupleWithCard());

        $this->travelTo(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone')));
        try {
            $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $card->id,
                'description' => 'Compra cartão',
                'amount' => '80.00',
                'payment_method' => null,
                'type' => 'expense',
                'date' => '2026-03-05',
                'reference_month' => 3,
                'reference_year' => 2026,
            ], [['category_id' => $category->id, 'amount' => '80.00']]);

            $stmt = CreditCardStatement::query()
                ->where('account_id', $card->id)
                ->where('reference_month', 3)
                ->where('reference_year', 2026)
                ->firstOrFail();

            $pay = $this->createTransactionWithSplits([
                'couple_id' => $couple->id,
                'user_id' => $user->id,
                'account_id' => $checking->id,
                'description' => 'Pagamento fatura',
                'amount' => '80.00',
                'payment_method' => 'Pix',
                'type' => 'expense',
                'date' => '2026-04-10',
                'reference_month' => 4,
                'reference_year' => 2026,
            ], [['category_id' => $invoiceCategory->id, 'amount' => '80.00']]);

            $stmt->paymentTransactions()->attach($pay->id);
            $stmt->refresh();
            $stmt->syncPaidMetadata();

            $this->actingAs($user)
                ->get(route('dashboard', ['period' => '2026-04']))
                ->assertOk()
                ->assertDontSee('rt-reminder-strip', false);
        } finally {
            $this->travelBack();
        }
    }
}
