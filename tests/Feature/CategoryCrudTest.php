<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_pode_criar_categoria_no_seu_casal(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $response = $this->actingAs($user)->post(route('categories.store'), [
            'name' => 'Alimentação',
            'type' => 'expense',
            'color' => '#ff0000',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', [
            'couple_id' => $couple->id,
            'name' => 'Alimentação',
            'type' => 'expense',
        ]);
    }

    public function test_criacao_de_categoria_exige_campos_obrigatorios(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $response = $this->actingAs($user)->post(route('categories.store'), []);

        $response->assertSessionHasErrors(['name', 'type']);
    }

    public function test_usuario_nao_pode_atualizar_categoria_de_outro_casal(): void
    {
        $coupleA = Couple::factory()->create();
        $coupleB = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $coupleA->id]);
        $categoryOther = Category::create([
            'couple_id' => $coupleB->id,
            'name' => 'Externa',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->put(route('categories.update', $categoryOther), [
            'name' => 'Invadida',
            'type' => 'income',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('categories', [
            'id' => $categoryOther->id,
            'name' => 'Externa',
        ]);
    }

    public function test_usuario_pode_atualizar_categoria_do_proprio_casal(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Antiga',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => 'Nova',
            'type' => 'income',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nova',
            'type' => 'income',
        ]);
    }

    public function test_nao_pode_editar_nem_excluir_categoria_sistema_pagamento_fatura(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $fixed = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'color' => '#64748b',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $this->actingAs($user)->put(route('categories.update', $fixed), [
            'name' => 'Outro nome',
            'type' => 'expense',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseHas('categories', [
            'id' => $fixed->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
        ]);

        $this->actingAs($user)->delete(route('categories.destroy', $fixed))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $fixed->id]);
    }

    public function test_nao_pode_renomear_categoria_para_nome_reservado(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Livre',
            'type' => 'expense',
            'color' => '#111',
        ]);

        $this->actingAs($user)->put(route('categories.update', $category), [
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Livre',
        ]);
    }
}
