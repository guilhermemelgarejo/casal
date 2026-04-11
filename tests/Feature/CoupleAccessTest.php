<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoupleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_sem_casal_e_redirecionado_do_dashboard(): void
    {
        $user = User::factory()->create(['couple_id' => null]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('couple.index'));
        $response->assertSessionHas('error');
    }

    public function test_usuario_com_casal_acessa_o_dashboard(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_billing_owner_nao_pode_sair_do_casal_sem_transferir_com_outro_membro(): void
    {
        $couple = Couple::factory()->create();
        $owner = User::factory()->create(['couple_id' => $couple->id]);
        $partner = User::factory()->create(['couple_id' => $couple->id]);
        $couple->forceFill(['billing_owner_user_id' => $owner->id])->save();

        $response = $this->actingAs($owner)->post(route('couple.leave'));

        $response->assertRedirect(route('couple.index'));
        $response->assertSessionHas('error');
        $this->assertSame($couple->id, $owner->fresh()->couple_id);
    }

    public function test_billing_owner_pode_sair_apos_transferir_responsabilidade(): void
    {
        $couple = Couple::factory()->create();
        $owner = User::factory()->create(['couple_id' => $couple->id]);
        $partner = User::factory()->create(['couple_id' => $couple->id]);
        $couple->forceFill(['billing_owner_user_id' => $owner->id])->save();

        $this->actingAs($owner)->post(route('couple.transfer-billing-owner'), [
            'billing_owner_user_id' => $partner->id,
        ])->assertSessionHas('success');

        $response = $this->actingAs($owner)->post(route('couple.leave'));

        $response->assertRedirect(route('couple.index'));
        $response->assertSessionHas('success');
        $this->assertNull($owner->fresh()->couple_id);
        $this->assertSame($partner->id, $couple->fresh()->billing_owner_user_id);
    }

    public function test_membro_que_nao_e_billing_owner_pode_sair(): void
    {
        $couple = Couple::factory()->create();
        $owner = User::factory()->create(['couple_id' => $couple->id]);
        $partner = User::factory()->create(['couple_id' => $couple->id]);
        $couple->forceFill(['billing_owner_user_id' => $owner->id])->save();

        $response = $this->actingAs($partner)->post(route('couple.leave'));

        $response->assertRedirect(route('couple.index'));
        $response->assertSessionHas('success');
        $this->assertNull($partner->fresh()->couple_id);
    }

    public function test_ultimo_membro_pode_sair_e_limpa_billing_owner_do_casal(): void
    {
        $couple = Couple::factory()->create();
        $only = User::factory()->create(['couple_id' => $couple->id]);
        $couple->forceFill(['billing_owner_user_id' => $only->id])->save();

        $response = $this->actingAs($only)->post(route('couple.leave'));

        $response->assertRedirect(route('couple.index'));
        $response->assertSessionHas('success');
        $this->assertNull($only->fresh()->couple_id);
        $this->assertNull($couple->fresh()->billing_owner_user_id);
    }
}
