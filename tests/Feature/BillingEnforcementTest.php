<?php

namespace Tests\Feature;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function enforceBilling(): void
    {
        config([
            'duozen.billing_disabled' => false,
            'duozen.stripe_price_id' => 'price_test_123',
            'cashier.secret' => 'sk_test_123',
        ]);
    }

    public function test_dashboard_redirects_to_billing_when_couple_has_no_active_subscription(): void
    {
        $this->enforceBilling();

        $couple = Couple::factory()->create();
        $user = User::factory()->create(['couple_id' => $couple->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/billing');
    }

    public function test_dashboard_is_allowed_when_any_member_has_active_subscription(): void
    {
        $this->enforceBilling();

        $couple = Couple::factory()->create();

        $owner = User::factory()->create(['couple_id' => $couple->id]);
        $member = User::factory()->create(['couple_id' => $couple->id]);

        $owner->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => config('duozen.stripe_price_id'),
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($member)
            ->get('/dashboard')
            ->assertOk();
    }
}

