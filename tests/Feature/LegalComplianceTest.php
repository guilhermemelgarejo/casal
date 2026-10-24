<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function subscribe(User $user, ?string $endsAt = null): void
    {
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_test',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_membro_com_assinatura_renovando_nao_pode_sair_do_casal(): void
    {
        $couple = Couple::factory()->create();
        $payer = User::factory()->create(['couple_id' => $couple->id]);
        User::factory()->create(['couple_id' => $couple->id]);
        $this->subscribe($payer);

        $this->actingAs($payer)->post(route('couple.leave'))
            ->assertRedirect(route('couple.index'))
            ->assertSessionHas('error');

        $this->assertSame($couple->id, $payer->fresh()->couple_id);
    }

    public function test_membro_com_assinatura_cancelada_pode_sair_e_o_codigo_muda(): void
    {
        $couple = Couple::factory()->create();
        $payer = User::factory()->create(['couple_id' => $couple->id]);
        User::factory()->create(['couple_id' => $couple->id]);
        $this->subscribe($payer, now()->addDays(10)->toDateTimeString());
        $oldCode = $couple->invite_code;

        $this->actingAs($payer)->post(route('couple.leave'))->assertSessionHas('success');

        $this->assertNull($payer->fresh()->couple_id);
        $this->assertNotSame($oldCode, $couple->fresh()->invite_code);

        $this->actingAs($payer->fresh())->post('/couple/join', ['invite_code' => $oldCode])
            ->assertSessionHasErrors('invite_code');
    }

    public function test_membro_pode_gerar_novo_codigo_de_convite(): void
    {
        $couple = Couple::factory()->create();
        $member = User::factory()->create(['couple_id' => $couple->id]);
        $oldCode = $couple->invite_code;

        $this->actingAs($member)->post(route('couple.invite-code.regenerate'))->assertSessionHas('success');

        $this->assertNotSame($oldCode, $couple->fresh()->invite_code);
    }

    public function test_convite_nao_e_enviado_quando_o_casal_esta_completo(): void
    {
        Mail::fake();
        $couple = Couple::factory()->create();
        $member = User::factory()->create(['couple_id' => $couple->id]);
        User::factory()->create(['couple_id' => $couple->id]);

        $this->actingAs($member)->post(route('couple.invite'), ['email' => 'alguem@example.com'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_exportacao_traz_apenas_lancamentos_do_proprio_casal(): void
    {
        $couple = Couple::factory()->create();
        $member = User::factory()->create(['couple_id' => $couple->id]);
        $other = Couple::factory()->create();
        $stranger = User::factory()->create(['couple_id' => $other->id]);

        foreach ([[$couple, $member, 'Mercado do casal'], [$other, $stranger, 'Compra de outro casal']] as [$c, $u, $description]) {
            $account = Account::create(['couple_id' => $c->id, 'name' => 'Conta', 'kind' => Account::KIND_REGULAR, 'color' => '#000']);
            $category = Category::create(['couple_id' => $c->id, 'name' => 'Alimentação', 'type' => 'expense', 'color' => '#000']);
            $transaction = Transaction::create([
                'couple_id' => $c->id, 'user_id' => $u->id, 'account_id' => $account->id,
                'description' => $description, 'amount' => '123.45', 'payment_method' => 'Pix',
                'type' => 'expense', 'date' => '2026-10-10', 'reference_month' => 10, 'reference_year' => 2026,
            ]);
            $transaction->syncCategorySplits([['category_id' => $category->id, 'amount' => '123.45']]);
        }

        $response = $this->actingAs($member)->get(route('couple.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Mercado do casal', $csv);
        $this->assertStringContainsString('123,45', $csv);
        $this->assertStringContainsString('Alimentação (123,45)', $csv);
        $this->assertStringNotContainsString('Compra de outro casal', $csv);
    }

    public function test_politica_de_privacidade_e_publica(): void
    {
        config(['backup.retention_days' => 0]);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Política de Privacidade')
            ->assertSee('art. 18 da LGPD', false)
            ->assertSee('[a preencher: prazo de guarda dos backups]', false);
    }

    public function test_politica_mostra_o_mesmo_prazo_usado_na_limpeza_dos_backups(): void
    {
        config(['backup.retention_days' => 90]);

        $this->get(route('legal.privacy'))
            ->assertSee('Cópias de segurança:</strong> até 90 dias', false)
            ->assertDontSee('prazo de guarda dos backups', false);
    }
}
