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

    public function test_nao_pode_excluir_categoria_vinculada_a_lancamentos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Supermercado',
            'type' => 'expense',
        ]);

        $tx = $couple->transactions()->create([
            'user_id' => $user->id,
            'description' => 'Compras',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-08-10',
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);
        $tx->syncCategorySplits([['category_id' => $category->id, 'amount' => '50.00']]);

        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_usuario_pode_excluir_categoria_sem_vinculos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Categoria Temporaria',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Categoria excluída!');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_falha_ao_excluir_categoria_exibe_alerta_e_nao_abre_modal_de_cadastro(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Supermercado',
            'type' => 'expense',
        ]);

        $tx = $couple->transactions()->create([
            'user_id' => $user->id,
            'description' => 'Compras',
            'amount' => '50.00',
            'type' => 'expense',
            'date' => '2026-08-10',
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);
        $tx->syncCategorySplits([['category_id' => $category->id, 'amount' => '50.00']]);

        $response = $this->actingAs($user)->delete(route('categories.destroy', $category), [
            '_form' => 'category-destroy',
        ]);
        $response->assertSessionHasErrors('category');

        $indexResponse = $this->actingAs($user)->get(route('categories.index'));
        $indexResponse->assertSee('Não foi possível excluir a categoria');
        $indexResponse->assertSee('Esta categoria possui movimentações, orçamentos ou modelos recorrentes vinculados e não pode ser excluída.');
        $indexResponse->assertSee('data-open-on-load="0"', false);
        $indexResponse->assertDontSee('data-open-on-load="1"');
    }

    public function test_categoria_com_orcamento_zerado_ou_removido_pode_ser_excluida(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Viagem',
            'type' => 'expense',
        ]);

        // Define orçamento inicial
        $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'amount' => '200.00',
        ])->assertSessionHasNoErrors();

        // Não pode excluir com orçamento > 0
        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertSessionHasErrors('category');

        // Zera orçamento (remove)
        $this->actingAs($user)->post(route('budgets.store'), [
            'category_id' => $category->id,
            'amount' => '0',
        ])->assertSessionHasNoErrors();

        // Agora pode excluir
        $this->actingAs($user)->delete(route('categories.destroy', $category))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
