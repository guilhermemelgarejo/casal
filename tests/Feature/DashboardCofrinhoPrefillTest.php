<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\FinancialProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCofrinhoPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_cofrinho_prefill_payload_for_aporte(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        Account::create([
            'couple_id' => $couple->id,
            'name' => 'Conta teste',
            'kind' => Account::KIND_REGULAR,
            'color' => '#333333',
            'balance' => '100.00',
        ]);
        Category::ensureSavingsCategoriesForCouple((int) $couple->id);
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Viagem',
            'target_amount' => '500.00',
            'color' => '#0ea5e9',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => '2026-04',
            'prefill_cofrinho' => $project->id,
            'prefill_cofrinho_kind' => 'aporte',
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-tx-cofrinho-prefill="[^"]+"/', $html);
        preg_match('/data-tx-cofrinho-prefill="([^"]*)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null);
        $payload = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        $this->assertIsArray($payload);
        $this->assertSame('aporte', $payload['kind']);
        $this->assertSame('expense', $payload['type']);
        $this->assertSame($project->id, $payload['financial_project_id']);
        $this->assertSame('Aporte: Viagem', $payload['description']);
    }

    public function test_dashboard_shows_blocked_message_when_only_credit_cards(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        Account::create([
            'couple_id' => $couple->id,
            'name' => 'Cartão X',
            'kind' => Account::KIND_CREDIT_CARD,
            'color' => '#111111',
            'balance' => '0',
            'credit_card_limit_total' => '1000.00',
            'credit_card_limit_available' => '1000.00',
            'credit_card_invoice_due_day' => 10,
        ]);
        Category::ensureSavingsCategoriesForCouple((int) $couple->id);
        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => 'Poupança',
            'target_amount' => null,
            'color' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'period' => '2026-04',
            'prefill_cofrinho' => $project->id,
            'prefill_cofrinho_kind' => 'retirada',
        ]));

        $response->assertOk();
        $response->assertSee('Aportes e retiradas de cofrinho só em conta corrente', false);
        $response->assertSee('data-tx-cofrinho-prefill=""', false);
    }
}
