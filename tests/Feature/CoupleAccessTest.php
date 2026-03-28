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
}
