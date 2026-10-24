<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class AdministratorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_couple_and_legacy_configuration_do_not_grant_admin_to_new_members(): void
    {
        $couple = Couple::factory()->create();
        config(['duozen.subscription_admin_couple_id' => $couple->id, 'duozen.admin_emails' => []]);
        $user = User::factory()->create(['couple_id' => $couple->id]);
        $this->assertFalse($user->isCasalAdmin());
        $this->actingAs($user)->get('/admin/assinaturas')->assertForbidden();
    }

    public function test_claiming_unused_allowlisted_email_does_not_grant_unverified_admin_or_billing_exemption(): void
    {
        config(['duozen.admin_emails' => ['admin@example.test'], 'duozen.billing_exempt_emails' => ['admin@example.test']]);
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => 'Teste', 'email' => 'admin@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->isCasalAdmin());
        $this->assertFalse($user->fresh()->isBillingExempt());
        $this->get('/admin/assinaturas')->assertForbidden();
    }

    public function test_verified_explicit_administrator_retains_access_independently_of_couple(): void
    {
        config(['duozen.admin_emails' => []]);
        $user = User::factory()->create(['couple_id' => null]);
        config(['duozen.admin_user_ids' => [$user->id]]);
        $this->assertTrue($user->fresh()->isCasalAdmin());
        $this->actingAs($user)->get('/admin/assinaturas')->assertOk();
        config(['duozen.admin_user_ids' => []]);
        $this->assertFalse($user->fresh()->isCasalAdmin());
    }

    public function test_unverified_user_does_not_receive_admin_even_with_explicit_id(): void
    {
        $user = User::factory()->unverified()->create();
        config(['duozen.admin_user_ids' => [$user->id]]);
        $this->assertFalse($user->isCasalAdmin());
    }

    public function test_changing_email_suspends_existing_administrator_access_until_verification(): void
    {
        $user = User::factory()->create();
        config(['duozen.admin_user_ids' => [$user->id]]);
        $this->actingAs($user)->patch('/profile', ['name' => 'Teste', 'email' => 'new-address@example.test']);
        $this->assertFalse($user->fresh()->isCasalAdmin());
    }
}
