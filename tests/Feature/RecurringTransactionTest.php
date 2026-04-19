<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{couple: Couple, user: User, category: Category, account: Account}
     */
    private function seedCoupleExpenseSetup(): array
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Moradia',
            'type' => 'expense',
            'color' => '#111111',
        ]);
        $account = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta corrente',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
        ]);

        return ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account];
    }

    public function test_lembrete_aparece_desde_o_primeiro_dia_do_mes_mesmo_antes_de_day_of_month(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel',
            'amount' => '100.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 25,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '100.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 3, 10, 0, 0, config('app.timezone')));

        try {
            $this->assertTrue($rt->fresh()->shouldShowReminder(Carbon::now()));
        } finally {
            $this->travelBack();
        }
    }

    public function test_lembrete_nao_aparece_quando_ja_ha_lancamento_no_mes(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel',
            'amount' => '100.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '100.00'],
        ]);

        $this->createTransactionWithSplits([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'description' => 'Aluguel',
            'amount' => '100.00',
            'payment_method' => 'Pix',
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'recurring_transaction_id' => $rt->id,
        ], [
            ['category_id' => $category->id, 'amount' => '100.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 15, 10, 0, 0, config('app.timezone')));

        try {
            $this->assertFalse($rt->fresh()->shouldShowReminder(Carbon::now()));
        } finally {
            $this->travelBack();
        }
    }

    public function test_recurring_index_nao_mostra_painel_de_lembretes(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Mensalidade',
            'amount' => '80.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 10,
            'is_active' => true,
        ])->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '80.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 12, 10, 0, 0, config('app.timezone')));

        try {
            $html = $this->actingAs($user)->get(route('recurring-transactions.index'))->assertOk()->getContent();

            $this->assertStringNotContainsString('rt-reminder-card', $html);
            $this->assertStringNotContainsString('Lembretes deste mês', $html);
            $this->assertStringContainsString('Mensalidade', $html);
        } finally {
            $this->travelBack();
        }
    }

    public function test_painel_lembretes_vermelho_quando_dia_sugerido_da_recorrencia_ja_passou(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Mensalidade',
            'amount' => '80.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 10,
            'is_active' => true,
        ])->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '80.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 12, 10, 0, 0, config('app.timezone')));

        try {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('rt-reminder-card--overdue', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_painel_lembretes_sem_vermelho_antes_do_dia_sugerido(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Mensalidade',
            'amount' => '80.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 10,
            'is_active' => true,
        ])->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '80.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 8, 10, 0, 0, config('app.timezone')));

        try {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('rt-reminder-card--overdue', false);
        } finally {
            $this->travelBack();
        }
    }

    public function test_is_reminder_overdue_for_calendar_month(): void
    {
        ['couple' => $couple, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'X',
            'amount' => '1.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 15,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '1.00'],
        ]);

        $this->assertFalse($rt->isReminderOverdueForCalendarMonth(Carbon::create(2026, 4, 15, 12, 0, 0, config('app.timezone'))));
        $this->assertTrue($rt->isReminderOverdueForCalendarMonth(Carbon::create(2026, 4, 16, 12, 0, 0, config('app.timezone'))));
    }

    public function test_index_expoe_payloads_de_edicao_por_id_em_script(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel',
            'amount' => '1500.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '1500.00'],
        ]);

        $html = $this->actingAs($user)->get(route('recurring-transactions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.__RT_EDIT_BY_ID__', $html);
        $this->assertStringContainsString('"'.(string) $rt->id.'"', $html);
        $this->assertStringContainsString('data-rt-edit-id="'.$rt->id.'"', $html);
    }

    public function test_store_creates_model_and_splits(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $this->actingAs($user)->post(route('recurring-transactions.store'), [
            '_form' => 'recurring-transactions',
            'description' => 'Aluguel',
            'amount' => '1500.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'day_of_month' => 5,
            'is_active' => '1',
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '1500.00'],
            ],
        ])->assertRedirect();

        $rt = RecurringTransaction::query()->where('couple_id', $couple->id)->first();
        $this->assertNotNull($rt);
        $this->assertSame('Aluguel', $rt->description);
        $this->assertSame(RecurringTransaction::MODE_REMINDER, $rt->generation_mode);
        $this->assertSame(1, $rt->categorySplits()->count());
    }

    public function test_transactions_index_mostra_painel_de_lembretes_quando_pendente(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Aluguel',
            'amount' => '100.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ])->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '100.00'],
        ]);

        $this->travelTo(Carbon::create(2026, 4, 10, 10, 0, 0, config('app.timezone')));

        try {
            $html = $this->actingAs($user)->get(route('dashboard', [
                'period' => '2026-04',
            ]))->assertOk()->getContent();

            $this->assertStringContainsString('rt-reminder-card', $html);
            $this->assertStringContainsString('Lembretes deste mês', $html);
            $this->assertStringContainsString('Gerenciar modelos', $html);
            $this->assertStringContainsString('rt-reminder-btn--header', $html);
            $this->assertStringContainsString('Aluguel', $html);
            $this->assertStringContainsString('Dia previsto:', $html);
            $this->assertStringContainsString('05/04/2026', $html);
        } finally {
            $this->travelBack();
        }
    }

    public function test_transactions_index_includes_recurring_prefill_payload(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Streaming',
            'amount' => '39.90',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 5,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '39.90'],
        ]);

        $html = $this->actingAs($user)->get(route('dashboard', [
            'prefill_recurring' => $rt->id,
            'period' => '2026-04',
        ]))->assertOk()->getContent();

        $rt->load('categorySplits');
        $expectedPayload = $rt->toTransactionPrefillPayload(Carbon::createFromDate(2026, 4, 1));
        $this->assertMatchesRegularExpression('/data-tx-recurring-prefill="([^"]*)"/', $html);
        preg_match('/data-tx-recurring-prefill="([^"]*)"/', $html, $m);
        $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame($expectedPayload, json_decode($decoded, true));
    }

    public function test_store_redireciona_sem_prefill_recurring_para_a_modal_nao_reabrir(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Modelo',
            'amount' => '50.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 1,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '50.00'],
        ]);

        $from = route('dashboard', [
            'period' => '2026-07',
            'prefill_recurring' => $rt->id,
            'account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->from($from)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '50.00'],
            ],
            'account_id' => $account->id,
            'description' => 'Lançamento manual',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-07-10',
            'reference_month' => 7,
            'reference_year' => 2026,
            'recurring_template_id' => $rt->id,
        ]);

        $response->assertSessionHas('success');
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('prefill_recurring', $location);
        $this->assertStringContainsString('period=2026-07', $location);
        $this->assertStringContainsString('account_id='.$account->id, $location);
    }

    public function test_store_sets_recurring_transaction_id_when_single_installment_and_template(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Modelo',
            'amount' => '50.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_ACCOUNT,
            'account_id' => $account->id,
            'payment_method' => 'Pix',
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 1,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '50.00'],
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'account',
            'payment_method' => 'Pix',
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '50.00'],
            ],
            'account_id' => $account->id,
            'description' => 'Lançamento manual',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-04-10',
            'reference_month' => 4,
            'reference_year' => 2026,
            'recurring_template_id' => $rt->id,
        ])->assertSessionHasNoErrors();

        $tx = Transaction::query()->where('couple_id', $couple->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame((int) $rt->id, (int) $tx->recurring_transaction_id);
    }

    public function test_store_does_not_set_recurring_link_when_parcelado(): void
    {
        ['couple' => $couple, 'user' => $user, 'category' => $category, 'account' => $account] = $this->seedCoupleExpenseSetup();

        $card = Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#444444',
        ]);

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => 'Modelo',
            'amount' => '90.00',
            'type' => 'expense',
            'funding' => RecurringTransaction::FUNDING_CREDIT_CARD,
            'account_id' => $card->id,
            'payment_method' => null,
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => 1,
            'is_active' => true,
        ]);
        $rt->syncCategorySplits([
            ['category_id' => $category->id, 'amount' => '90.00'],
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'funding' => 'credit_card',
            'category_allocations' => [
                ['category_id' => $category->id, 'amount' => '90.00'],
            ],
            'account_id' => $card->id,
            'description' => 'Compra',
            'amount' => '90.00',
            'installments' => '3',
            'type' => 'expense',
            'date' => '2026-04-09',
            'reference_month' => 5,
            'reference_year' => 2026,
            'recurring_template_id' => $rt->id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            Transaction::query()
                ->where('couple_id', $couple->id)
                ->whereNotNull('recurring_transaction_id')
                ->doesntExist()
        );
    }
}
