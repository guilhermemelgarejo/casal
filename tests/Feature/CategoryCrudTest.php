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

    public function test_usuario_pode_desativar_e_reativar_categoria(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $category = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Restaurantes',
            'type' => 'expense',
            'is_active' => true,
        ]);

        // Desativar
        $res = $this->actingAs($user)->patch(route('categories.toggle-active', $category));
        $res->assertSessionHas('success', 'Categoria desativada com sucesso.');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        // Reativar
        $res = $this->actingAs($user)->patch(route('categories.toggle-active', $category));
        $res->assertSessionHas('success', 'Categoria reativada com sucesso.');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_nao_pode_desativar_categoria_do_sistema(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $fixed = Category::create([
            'couple_id' => $couple->id,
            'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
            'type' => 'expense',
            'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
            'is_active' => true,
        ]);

        $res = $this->actingAs($user)->patch(route('categories.toggle-active', $fixed));
        $res->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', [
            'id' => $fixed->id,
            'is_active' => true,
        ]);
    }

    public function test_categorias_inativas_aparecem_na_secao_desativadas_da_index(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $activeCat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Categoria Ativa',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $inactiveCat = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Categoria Inativa',
            'type' => 'expense',
            'is_active' => false,
        ]);

        $res = $this->actingAs($user)->get(route('categories.index'));
        $res->assertSee('Categoria Ativa');
        $res->assertSee('Categoria Inativa');
        $res->assertSee('Categorias desativadas');
    }

    public function test_categorias_inativas_nao_aparecem_no_modal_de_novo_lancamento(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);
        Category::create([
            'couple_id' => $couple->id,
            'name' => 'Supermercado Ativo',
            'type' => 'expense',
            'is_active' => true,
        ]);
        Category::create([
            'couple_id' => $couple->id,
            'name' => 'Lojas Inativo',
            'type' => 'expense',
            'is_active' => false,
        ]);

        $res = $this->actingAs($user)->get(route('transactions.index'));
        $res->assertOk();
        $categoriesInPayload = view()->shared('categories');
        $this->assertTrue($categoriesInPayload->contains('name', 'Supermercado Ativo'));
        $this->assertFalse($categoriesInPayload->contains('name', 'Lojas Inativo'));
    }

    public function test_nao_pode_editar_nem_excluir_nem_desativar_categorias_de_cofrinho_e_rendimentos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        Category::ensureSavingsCategoriesForCouple($couple->id);

        $investments = Category::investmentsForCouple($couple->id);
        $withdrawal = Category::piggyBankWithdrawalForCouple($couple->id);
        $yield = Category::accountYieldForCouple($couple->id);

        foreach ([$investments, $withdrawal, $yield] as $systemCat) {
            $this->assertNotNull($systemCat);
            $this->assertTrue($systemCat->isImmutableSystemCategory());
            $this->assertTrue($systemCat->isSystemCategory());

            // Tentativa de update
            $this->actingAs($user)->put(route('categories.update', $systemCat), [
                'name' => 'Nome Alterado',
                'type' => $systemCat->type,
            ])->assertSessionHasErrors('name');

            // Tentativa de toggle-active
            $this->actingAs($user)->patch(route('categories.toggle-active', $systemCat))
                ->assertSessionHasErrors('category');

            // Tentativa de destroy
            $this->actingAs($user)->delete(route('categories.destroy', $systemCat))
                ->assertSessionHasErrors('category');

            $this->assertDatabaseHas('categories', [
                'id' => $systemCat->id,
                'name' => $systemCat->name,
                'is_active' => true,
            ]);
        }
    }

    public function test_nao_pode_criar_nem_renomear_para_nome_rendimentos(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        // Tentativa de store
        $this->actingAs($user)->post(route('categories.store'), [
            'name' => Category::NAME_ACCOUNT_YIELD,
            'type' => 'income',
        ])->assertSessionHasErrors('name');

        // Tentativa de update
        $custom = Category::create([
            'couple_id' => $couple->id,
            'name' => 'Extra',
            'type' => 'income',
        ]);

        $this->actingAs($user)->put(route('categories.update', $custom), [
            'name' => Category::NAME_ACCOUNT_YIELD,
            'type' => 'income',
        ])->assertSessionHasErrors('name');
    }

    public function test_categorias_de_sistema_exibem_badge_e_ocultam_acoes_de_edicao(): void
    {
        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        Category::ensureSavingsCategoriesForCouple($couple->id);

        $res = $this->actingAs($user)->get(route('categories.index'));
        $res->assertOk();

        // Deve conter o badge Sistema
        $res->assertSee('Sistema');
        $res->assertSee('Resgates de cofrinho — gerenciada pelo sistema.');
        $res->assertSee('Rendimentos de conta — gerenciada pelo sistema.');
        $res->assertSee('Aportes em cofrinho — gerenciada pelo sistema.');
    }
}
