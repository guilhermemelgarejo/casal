<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_casal_define_sessao_de_onboarding(): void
    {
        $user = User::factory()->create(['couple_id' => null]);

        $this->actingAs($user)->post(route('couple.create'), [
            'name' => 'Casal Teste Tour',
        ])->assertRedirect(route('couple.index'));

        $this->assertTrue(session()->get('duozen_onboarding_tour'));
    }

    public function test_dismiss_onboarding_limpa_sessao(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $this->actingAs($user)
            ->withSession(['duozen_onboarding_tour' => true])
            ->post(route('onboarding.dismiss'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull(session()->get('duozen_onboarding_tour'));
    }

    public function test_restart_onboarding_define_sessao_e_redireciona_ao_painel(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $response = $this->actingAs($user)->post(route('onboarding.restart'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');
        $this->assertTrue(session()->get('duozen_onboarding_tour'));
    }
}
