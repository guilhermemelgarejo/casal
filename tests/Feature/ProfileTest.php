<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_confirmed_email_has_visible_status_and_confirmation_date(): void
    {
        $user = User::factory()->create(['email_verified_at' => '2026-10-20 12:00:00']);

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Verificação e acesso')
            ->assertSee('E-mail confirmado')
            ->assertSee('Confirmado em 20/10/2026')
            ->assertDontSee('Aguardando confirmação')
            ->assertDontSee('Reenviar confirmação');
    }

    public function test_email_and_google_states_share_one_section_without_confusing_connection_with_verification(): void
    {
        config(['services.google.enabled' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://casal.localhost/auth/google/callback']);
        $user = User::factory()->unverified()->create();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'profile-status-google']);
        $before = $user->fresh()->getAttributes();

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSeeInOrder(['Verificação e acesso', 'E-mail da conta', 'Aguardando confirmação', 'Conta Google', 'Conectado'])
            ->assertSee('Reenviar confirmação')
            ->assertSee('Gerenciar conexão')
            ->assertDontSee('E-mail confirmado');

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_google_not_connected_is_visible_next_to_confirmed_email(): void
    {
        config(['services.google.enabled' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://casal.localhost/auth/google/callback']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSeeInOrder(['Verificação e acesso', 'E-mail confirmado', 'Conta Google', 'Não conectado'])
            ->assertSee('Conectar Google');
    }

    public function test_resending_verification_shows_feedback_in_access_section(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/profile')->post(route('verification.send'))->assertRedirect('/profile');
        $this->get('/profile')->assertOk()
            ->assertSee('Aguardando confirmação')
            ->assertSee('Link enviado. Confira sua caixa de entrada e a pasta de spam.');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_status_changes_to_confirmed_after_using_verification_link(): void
    {
        $user = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($link)->assertRedirect();
        $this->get('/profile')->assertOk()->assertSee('E-mail confirmado')
            ->assertDontSee('Aguardando confirmação')->assertDontSee('Reenviar confirmação');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
