<?php

namespace Tests\Feature\Auth;

use App\Models\Couple;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\Support\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $google;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.enabled' => true,
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://casal.localhost/auth/google/callback',
        ]);
        Notification::fake();
        $this->google = new MockHandler;
        // Usa o provider real, incluindo state, PKCE, troca do código e mapeamento de sub.
        // Somente a rede Google é substituída; nenhuma requisição real é permitida.
        Socialite::shouldReceive('buildProvider')->andReturnUsing(function ($class, $config) {
            return (new GoogleProvider(request(), $config['client_id'], $config['client_secret'], $config['redirect']))
                ->setHttpClient(new Client(['handler' => HandlerStack::create($this->google)]));
        });
    }

    private function queueIdentity(array $overrides = []): void
    {
        $this->google->append(
            new Response(200, [], json_encode(['access_token' => 'test-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600])),
            new Response(200, [], json_encode($overrides + [
                'sub' => 'google-sub-123', 'name' => 'Pessoa Google', 'email' => 'google.person@gmail.com', 'email_verified' => true,
            ])),
        );
    }

    private function callbackUrl(string $state = ''): string
    {
        return route('google.callback').'?'.http_build_query([
            'state' => $state ?: session('state'), 'code' => 'test-authorization-code',
        ]);
    }

    private function googleUser(array $attributes = [], string $sub = 'google-sub-123'): User
    {
        $user = User::factory()->create($attributes + ['password' => null]);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => $sub]);

        return $user;
    }

    private function pendingRegistration(array $overrides = []): array
    {
        return $overrides + [
            'sub' => 'google-sub-123', 'email' => 'google.person@gmail.com', 'name' => 'Pessoa Google',
            'authoritative_email' => true, 'invite_code' => null, 'at' => time(),
        ];
    }

    public function test_disabled_feature_hides_buttons_and_blocks_all_google_routes(): void
    {
        config(['services.google.enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('Continuar com Google');
        $this->get('/register')->assertOk()->assertDontSee('Continuar com Google');
        $this->get(route('google.redirect'))->assertNotFound();
        $this->get(route('google.callback'))->assertNotFound();
        $this->get(route('google.register'))->assertNotFound();
        $this->post(route('google.register.store'))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->post(route('google.link'))->assertNotFound();
        $this->post(route('google.confirm'))->assertNotFound();
        $this->delete(route('google.unlink'))->assertNotFound();
    }

    public function test_missing_credentials_keep_feature_disabled(): void
    {
        config(['services.google.client_secret' => '']);
        $this->get('/login')->assertDontSee('Continuar com Google');
        $this->get(route('google.redirect'))->assertNotFound();
    }

    public function test_enabled_buttons_and_redirect_use_only_identity_scopes_and_pkce(): void
    {
        $this->get('/login')->assertSee('Continuar com Google');
        $this->get('/register?invite_code=example')->assertSee('invite_code=example');
        $response = $this->get(route('google.redirect', ['invite_code' => 'example']));
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('accounts.google.com', parse_url($response->headers->get('Location'), PHP_URL_HOST));
        $this->assertEqualsCanonicalizing(['openid', 'email', 'profile'], explode(' ', $query['scope']));
        $this->assertNotEmpty($query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('example', session('google_oauth.invite_code'));
        $this->assertNull(session('google_registration'));
    }

    public function test_callback_requires_an_initiated_flow_and_correct_state(): void
    {
        $this->get($this->callbackUrl('invented'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl('tampered'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame(0, $this->google->count());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_flow_and_google_cancellation_do_not_create_accounts(): void
    {
        $this->withSession(['google_oauth' => ['mode' => 'login', 'user_id' => null, 'at' => time() - 601]])
            ->get($this->callbackUrl('old'))->assertSessionHasErrors('google');
        $this->get(route('google.redirect'));
        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionMissing('google_oauth');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_identity_waits_for_terms_and_never_stores_tokens_in_session(): void
    {
        $this->queueIdentity();
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl())->assertRedirect(route('google.register'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertSame('google-sub-123', session('google_registration.sub'));
        $this->assertStringNotContainsString('test-access-token', json_encode(session()->all()));
        $this->assertNull(session('state'));
        $this->assertNull(session('code_verifier'));
        $this->get(route('google.register'))->assertOk()->assertSee('google.person@gmail.com');
        $this->post(route('google.register.store'), ['name' => 'Pessoa'])->assertSessionHasErrors('terms');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_uses_server_identity_accepts_terms_and_preserves_invite(): void
    {
        $couple = Couple::factory()->create();
        User::factory()->create(['couple_id' => $couple->id]);
        $this->queueIdentity();
        $this->get(route('google.redirect', ['invite_code' => $couple->invite_code]));
        $this->get($this->callbackUrl());
        Log::spy();
        Log::shouldReceive('channel')->andReturnSelf();
        $oldSession = session()->getId();
        $this->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1, 'email' => 'attacker@example.com', 'sub' => 'attacker'])
            ->assertRedirect('/dashboard')->assertSessionMissing('google_registration');
        $user = User::where('email', 'google.person@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame($couple->id, $user->couple_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'google-sub-123']);
        Notification::assertNothingSent();
        Log::shouldHaveReceived('info')->with('Termos aceitos no cadastro', \Mockery::on(fn ($data) => $data['user_id'] === $user->id && $data['provider'] === 'google' && isset($data['terms_version'], $data['accepted_at'])))->once();
    }

    public function test_full_or_invalid_invite_rolls_back_user_and_identity(): void
    {
        $couple = Couple::factory()->create();
        User::factory()->count(2)->create(['couple_id' => $couple->id]);
        foreach ([$couple->invite_code, 'invalid-invite'] as $invite) {
            $this->withSession(['google_registration' => $this->pendingRegistration(['invite_code' => $invite])])
                ->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1])->assertSessionHasErrors('invite_code');
            $this->assertDatabaseCount('users', 2);
            $this->assertDatabaseCount('social_accounts', 0);
        }
    }

    public function test_non_authoritative_external_email_still_receives_local_verification(): void
    {
        $this->queueIdentity(['email' => 'person@example.com']);
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl());
        $this->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1]);
        $user = User::where('email', 'person@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_workspace_email_can_be_marked_verified(): void
    {
        $this->queueIdentity(['email' => 'person@example.com', 'hd' => 'example.com']);
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl());
        $this->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1]);
        $this->assertTrue(User::where('email', 'person@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->queueIdentity(['email_verified' => false]);
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl())->assertSessionHasErrors('google')->assertSessionMissing('google_registration');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_subject_email_or_reserved_domain_cannot_create_pending_identity(): void
    {
        foreach ([['sub' => null], ['email' => null], ['email' => 'deleted+fake@users.duozen.invalid']] as $identity) {
            $this->queueIdentity($identity);
            $this->get(route('google.redirect'));
            $this->get($this->callbackUrl())->assertSessionHasErrors('google')->assertSessionMissing('google_registration');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provider_failure_does_not_expose_transport_secrets(): void
    {
        $this->google->append(new RequestException('private-access-token', new HttpRequest('POST', 'https://google.example/token')));
        $this->get(route('google.redirect'));
        Log::spy();
        $this->get($this->callbackUrl())->assertRedirect(route('login'))
            ->assertSessionHasErrors('google')->assertSessionMissing('google_oauth');
        Log::shouldHaveReceived('warning')->with('Falha na autenticação Google', ['exception_class' => RequestException::class])->once();
        $this->assertStringNotContainsString('private-access-token', json_encode(session()->all()));
    }

    public function test_google_routes_limit_attempts(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->get(route('google.redirect'))->assertRedirect();
        }
        $this->get(route('google.redirect'))->assertStatus(429);
    }

    public function test_matching_email_never_links_or_modifies_existing_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'google.person@gmail.com']);
        $before = $user->fresh()->getAttributes();
        $this->queueIdentity();
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl())->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_registration_rechecks_email_conflicts_after_callback(): void
    {
        User::factory()->create(['email' => 'google.person@gmail.com']);
        $this->withSession(['google_registration' => $this->pendingRegistration()])
            ->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1])->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_pending_registration_expires(): void
    {
        $this->withSession(['google_registration' => $this->pendingRegistration(['at' => time() - 601])])
            ->post(route('google.register.store'), ['name' => 'Pessoa', 'terms' => 1])
            ->assertRedirect(route('login'))->assertSessionMissing('google_registration');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_identity_logs_in_by_subject_without_overwriting_profile(): void
    {
        $user = $this->googleUser(['email' => 'local@example.com', 'name' => 'Nome local']);
        $before = $user->fresh()->getAttributes();
        $this->queueIdentity(['email' => 'changed@gmail.com']);
        $this->get(route('google.redirect'));
        $oldSession = session()->getId();
        $this->withSession(['url.intended' => '/profile'])->get($this->callbackUrl())->assertRedirect('/profile');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_external_intended_redirect_is_rejected(): void
    {
        $this->googleUser();
        $this->queueIdentity();
        $this->get(route('google.redirect'));
        $this->withSession(['url.intended' => '//evil.example/path'])->get($this->callbackUrl())->assertRedirect('/dashboard');
    }

    public function test_link_requires_current_password_and_never_changes_local_profile(): void
    {
        $user = User::factory()->unverified()->create();
        $before = $user->fresh()->getAttributes();
        $this->actingAs($user)->post(route('google.link'), ['current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('googleAccount', 'current_password');
        $this->queueIdentity();
        $this->post(route('google.link'), ['current_password' => 'password'])->assertRedirect();
        $this->get($this->callbackUrl())->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'google-sub-123']);
    }

    public function test_link_cannot_take_identity_from_another_user(): void
    {
        $owner = $this->googleUser();
        $other = User::factory()->create();
        $this->queueIdentity();
        $this->actingAs($other)->post(route('google.link'), ['current_password' => 'password']);
        $this->get($this->callbackUrl())->assertSessionHasErrors('google');
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $owner->id]);
    }

    public function test_link_callback_cannot_switch_between_authenticated_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->post(route('google.link'), ['current_password' => 'password']);
        $this->actingAs($second)->get($this->callbackUrl())->assertSessionHasErrors('google');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_only_profile_renders_confirmation_and_password_setup(): void
    {
        $user = $this->googleUser();
        $this->actingAs($user)->get('/profile')->assertOk()
            ->assertSee('Confirmar Google para definir senha')->assertSee('Conta Google')
            ->assertDontSee('id="update_password_current_password"', false);
        $this->get('/confirm-password')->assertOk()->assertSee('Confirmar com Google');
    }

    public function test_password_setup_requires_recent_confirmation_for_same_user_and_purpose(): void
    {
        $user = $this->googleUser();
        $this->actingAs($user);
        $payload = ['password' => 'new-password', 'password_confirmation' => 'new-password'];
        foreach ([[], ['user_id' => $user->id, 'purpose' => 'delete', 'at' => time()],
            ['user_id' => $user->id + 1, 'purpose' => 'password', 'at' => time()],
            ['user_id' => $user->id, 'purpose' => 'password', 'at' => time() - 301]] as $proof) {
            $this->withSession(['google_confirmation' => $proof])->put('/password', $payload)
                ->assertSessionHasErrorsIn('updatePassword', 'current_password');
            $this->assertNull($user->fresh()->password);
        }
        $this->queueIdentity();
        $this->post(route('google.confirm'), ['purpose' => 'password']);
        $this->get($this->callbackUrl())->assertSessionHas('google_confirmation');
        $this->put('/password', $payload)->assertSessionHas('status', 'password-updated')->assertSessionMissing('google_confirmation');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_reauthentication_rejects_another_google_identity(): void
    {
        $user = $this->googleUser();
        $this->queueIdentity(['sub' => 'wrong-google-sub']);
        $this->actingAs($user)->post(route('google.confirm'), ['purpose' => 'delete']);
        $this->get($this->callbackUrl())->assertSessionHasErrors('google')->assertSessionMissing('google_confirmation');
    }

    public function test_reauthentication_can_confirm_a_password_protected_destination(): void
    {
        $user = $this->googleUser();
        $this->queueIdentity();
        $this->actingAs($user)->withSession(['url.intended' => '/profile'])
            ->post(route('google.confirm'), ['purpose' => 'confirm']);
        $this->get($this->callbackUrl())->assertRedirect('/profile')->assertSessionHas('auth.password_confirmed_at');
    }

    public function test_email_change_requires_google_confirmation_and_keeps_identity(): void
    {
        $user = $this->googleUser();
        $payload = ['name' => $user->name, 'email' => 'new-local@example.com'];
        $this->actingAs($user)->patch('/profile', $payload)->assertSessionHasErrors('email');
        $this->assertNotSame($payload['email'], $user->fresh()->email);
        $this->queueIdentity();
        $this->post(route('google.confirm'), ['purpose' => 'email']);
        $this->get($this->callbackUrl());
        $this->patch('/profile', $payload)->assertSessionHas('status', 'profile-updated')->assertSessionMissing('google_confirmation');
        $this->assertSame($payload['email'], $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id]);
    }

    public function test_unlink_requires_password_and_does_not_remove_last_access_method(): void
    {
        $user = $this->googleUser();
        $this->actingAs($user)->delete(route('google.unlink'))->assertSessionHasErrors('google');
        $user->update(['password' => 'local-password']);
        $this->delete(route('google.unlink'), ['current_password' => 'wrong'])->assertSessionHasErrorsIn('googleAccount', 'current_password');
        $this->assertDatabaseCount('social_accounts', 1);
        $this->delete(route('google.unlink'), ['current_password' => 'local-password'])->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertTrue(Hash::check('local-password', $user->fresh()->password));
    }

    public function test_google_only_deletion_requires_confirmation_and_revokes_identity(): void
    {
        $user = $this->googleUser();
        $this->actingAs($user)->delete('/profile')->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertDatabaseCount('social_accounts', 1);
        $this->queueIdentity();
        $this->post(route('google.confirm'), ['purpose' => 'delete']);
        $this->get($this->callbackUrl());
        $this->delete('/profile')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_anonymized_deleted_profile_cannot_be_reopened_using_old_google_identity(): void
    {
        $user = $this->googleUser();
        // Simula um perfil anonimizado cujo vínculo antigo ainda existe.
        DB::table('users')->where('id', $user->id)->update(['email' => 'deleted+test@users.duozen.invalid']);
        $this->queueIdentity();
        $this->get(route('google.redirect'));
        $this->get($this->callbackUrl())->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_only_user_can_use_email_password_recovery(): void
    {
        $user = $this->googleUser(['email' => 'person@example.com']);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email,
            'password' => 'recovered-password', 'password_confirmation' => 'recovered-password'])
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('recovered-password', $user->fresh()->password));
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_google_only_user_cannot_login_with_empty_local_password(): void
    {
        $user = $this->googleUser();
        $this->post('/login', ['email' => $user->email, 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
