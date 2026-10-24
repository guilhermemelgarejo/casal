<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class CoupleMembershipRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_member_cannot_replace_couple_through_create_or_join(): void
    {
        $couple = Couple::factory()->create();
        $other = Couple::factory()->create();
        $owner = User::factory()->create(['couple_id' => $couple->id]);
        User::factory()->create(['couple_id' => $couple->id]);
        $couple->forceFill(['billing_owner_user_id' => $owner->id])->save();
        $this->actingAs($owner)->post('/couple/create', ['name' => 'Outro casal'])->assertSessionHasErrors('name');
        $this->post('/couple/join', ['invite_code' => $other->invite_code])->assertSessionHasErrors('invite_code');
        $this->assertSame(2, Couple::count());
        $this->assertSame($couple->id, $owner->fresh()->couple_id);
        $this->assertSame($owner->id, $couple->fresh()->billing_owner_user_id);
    }

    public function test_unattached_user_can_create_couple_with_all_system_categories(): void
    {
        $user = User::factory()->create(['couple_id' => null]);
        $this->actingAs($user)->post('/couple/create', ['name' => 'Novo casal'])
            ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
        $couple = $user->fresh()->couple;
        $this->assertNotNull($couple);
        $this->assertNotNull(Category::creditCardInvoicePaymentForCouple($couple->id));
        $this->assertNotNull(Category::internalTransferExpenseForCouple($couple->id));
        $this->assertNotNull(Category::investmentsForCouple($couple->id));
    }

    public function test_join_enforces_two_members_and_does_not_move_user_on_failure(): void
    {
        $couple = Couple::factory()->create();
        User::factory()->count(2)->create(['couple_id' => $couple->id]);
        $user = User::factory()->create(['couple_id' => null]);
        $this->actingAs($user)->post('/couple/join', ['invite_code' => $couple->invite_code])
            ->assertSessionHasErrors('invite_code');
        $this->assertNull($user->fresh()->couple_id);
        $this->assertSame(2, $couple->users()->count());
    }

    public function test_registration_with_full_invite_rolls_back_new_user(): void
    {
        $couple = Couple::factory()->create();
        User::factory()->count(2)->create(['couple_id' => $couple->id]);
        $this->post('/register', [
            'name' => 'Cadastro teste', 'email' => 'registration-capacity@example.test',
            'password' => 'password', 'password_confirmation' => 'password', 'invite_code' => $couple->invite_code,
        ])->assertSessionHasErrors('invite_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'registration-capacity@example.test']);
    }

    public function test_registration_with_available_invite_joins_the_same_locked_membership_flow(): void
    {
        $couple = Couple::factory()->create();
        User::factory()->create(['couple_id' => $couple->id]);
        $this->post('/register', [
            'name' => 'Cadastro teste', 'email' => 'registration-open@example.test',
            'password' => 'password', 'password_confirmation' => 'password', 'invite_code' => $couple->invite_code,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'registration-open@example.test', 'couple_id' => $couple->id]);
        $this->assertSame(2, $couple->users()->count());
    }
}
