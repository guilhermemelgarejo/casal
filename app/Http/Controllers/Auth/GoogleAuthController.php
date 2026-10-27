<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CoupleMembershipService;
use App\Support\GoogleLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $data = $request->validate(['invite_code' => ['nullable', 'string', 'max:100']]);

        return $this->begin($request, 'login', ['invite_code' => $data['invite_code'] ?? null]);
    }

    public function link(Request $request): RedirectResponse
    {
        $request->validateWithBag('googleAccount', ['current_password' => ['required', 'current_password']]);

        return $this->begin($request, 'link');
    }

    public function reauthenticate(Request $request): RedirectResponse
    {
        $data = $request->validate(['purpose' => ['required', Rule::in(['password', 'delete', 'email', 'confirm'])]]);
        abort_unless($request->user()->socialAccounts()->where('provider', 'google')->exists(), 403);

        return $this->begin($request, 'reauthenticate', ['purpose' => $data['purpose']]);
    }

    private function begin(Request $request, string $mode, array $extra = []): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->forget(['google_registration', 'google_confirmation', 'state', 'code_verifier']);
        $request->session()->put('google_oauth', $extra + [
            'mode' => $mode,
            'user_id' => $request->user()?->id,
            'at' => time(),
        ]);

        return $this->provider()->with([
            'prompt' => $mode === 'reauthenticate' ? 'consent select_account' : 'select_account',
        ])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('google_oauth', []);
        if (! GoogleLogin::isRecent($flow['at'] ?? null)
            || ! in_array($flow['mode'] ?? null, ['login', 'link', 'reauthenticate'], true)
            || ($flow['user_id'] ?? null) !== $request->user()?->id) {
            return $this->fail($request, 'A solicitação expirou. Inicie novamente o acesso com Google.');
        }

        if ($request->has('error')) {
            return $this->fail($request, 'O acesso com Google foi cancelado. Você pode tentar novamente.');
        }

        try {
            // Socialite valida o state antes de trocar o código e buscar o perfil no Google.
            $remote = $this->provider()->user();
            $raw = $remote->getRaw();
            $identity = Validator::make([
                'sub' => $remote->getId(),
                'email' => strtolower(trim((string) $remote->getEmail())),
                'name' => mb_substr(trim((string) $remote->getName()), 0, 255),
            ], [
                'sub' => ['required', 'string', 'max:255', 'regex:/\A[\x21-\x7E]+\z/D'],
                'email' => ['required', 'email', 'max:255', 'not_regex:/@users\\.duozen\\.invalid$/i'],
                'name' => ['nullable', 'string', 'max:255'],
            ])->validate();
            if (($raw['email_verified'] ?? false) !== true) {
                return $this->fail($request, 'Use uma conta Google com e-mail verificado.');
            }
            // Endereços externos ao Gmail/Workspace ainda passam pela verificação local.
            $identity['authoritative_email'] = str_ends_with($identity['email'], '@gmail.com')
                || filled($raw['hd'] ?? null);
        } catch (Throwable $exception) {
            // A mensagem/trace do cliente HTTP pode conter códigos e tokens: não registrar.
            Log::warning('Falha na autenticação Google', ['exception_class' => $exception::class]);

            return $this->fail($request, 'Não foi possível confirmar o acesso com Google. Tente novamente.');
        } finally {
            $request->session()->forget(['state', 'code_verifier']);
        }

        if ($flow['mode'] === 'link') {
            return $this->completeLink($request, $identity);
        }

        $account = SocialAccount::query()->where('provider', 'google')
            ->where('provider_user_id', $identity['sub'])->first();

        if ($flow['mode'] === 'reauthenticate') {
            if (! $account || $account->user_id !== $request->user()->id) {
                return $this->fail($request, 'Escolha a conta Google vinculada ao seu perfil.');
            }
            $request->session()->regenerate();
            $request->session()->put('google_confirmation', [
                'user_id' => $request->user()->id, 'purpose' => $flow['purpose'], 'at' => time(),
            ]);
            if ($flow['purpose'] === 'confirm') {
                $request->session()->put('auth.password_confirmed_at', time());

                return redirect($this->intendedDestination($request));
            }

            return redirect()->route('profile.edit')->with('success', 'Identidade confirmada. Conclua a ação em até cinco minutos.');
        }

        if ($account) {
            $user = $account->user;
            if (! $user) {
                return $this->fail($request, 'Este vínculo não está disponível. Entre em contato pelo formulário de contato.');
            }

            return $this->login($request, $user);
        }

        if ($this->emailExists($identity['email'])) {
            return $this->fail($request, 'Este e-mail já possui cadastro. Entre com sua senha e vincule o Google em Meu Perfil. Se necessário, use Esqueceu a senha.');
        }

        $request->session()->regenerate();
        $request->session()->put('google_registration', $identity + [
            'invite_code' => $flow['invite_code'] ?? null, 'at' => time(),
        ]);

        return redirect()->route('google.register');
    }

    public function create(Request $request): View|RedirectResponse
    {
        $identity = $request->session()->get('google_registration', []);
        if (! GoogleLogin::isRecent($identity['at'] ?? null)) {
            return $this->fail($request, 'O cadastro expirou. Continue novamente com Google.');
        }

        return view('auth.google-register', ['identity' => $identity]);
    }

    public function store(Request $request, CoupleMembershipService $memberships): RedirectResponse
    {
        $identity = $request->session()->get('google_registration', []);
        if (! GoogleLogin::isRecent($identity['at'] ?? null)) {
            return $this->fail($request, 'O cadastro expirou. Continue novamente com Google.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'terms' => ['required', 'accepted'],
        ]);

        try {
            $user = DB::transaction(function () use ($identity, $data, $memberships) {
                if ($this->emailExists($identity['email']) || SocialAccount::query()
                    ->where('provider', 'google')->where('provider_user_id', $identity['sub'])->exists()) {
                    throw ValidationException::withMessages(['google' => 'Já existe um cadastro para esta conta. Volte ao login.']);
                }
                $user = User::create([
                    'name' => $data['name'], 'email' => $identity['email'], 'password' => null,
                ]);
                if ($identity['authoritative_email']) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
                $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => $identity['sub']]);
                if (filled($identity['invite_code'])) {
                    $memberships->join($user, $identity['invite_code']);
                }

                return $user;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return $this->fail($request, 'Já existe um cadastro para esta conta. Entre para continuar.');
        }

        Log::channel('legal')->info('Termos aceitos no cadastro', [
            'user_id' => $user->id, 'email' => $user->email, 'provider' => 'google',
            'terms_version' => config('duozen.legal.terms_updated_at'),
            'ip' => $request->ip(), 'user_agent' => (string) $request->userAgent(),
            'accepted_at' => now()->toIso8601String(),
        ]);
        event(new Registered($user));

        return $this->login($request, $user);
    }

    private function completeLink(Request $request, array $identity): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $identity) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                if ($user->socialAccounts()->where('provider', 'google')->exists()
                    || SocialAccount::query()->where('provider', 'google')->where('provider_user_id', $identity['sub'])->exists()) {
                    throw ValidationException::withMessages(['google' => 'Esta conta já possui um vínculo Google.']);
                }
                $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => $identity['sub']]);
            }, 3);
        } catch (UniqueConstraintViolationException|ValidationException) {
            return $this->fail($request, 'Este perfil ou esta conta Google já possui um vínculo.');
        }

        return redirect()->route('profile.edit')->with('success', 'Conta Google vinculada. Você já pode usá-la para entrar.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (! $request->user()->hasLocalPassword()) {
            throw ValidationException::withMessages(['google' => 'Defina uma senha antes de desvincular o Google.']);
        }
        $request->validateWithBag('googleAccount', ['current_password' => ['required', 'current_password']]);
        DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $user->socialAccounts()->where('provider', 'google')->delete();
        }, 3);
        $request->session()->forget(['google_confirmation', 'google_oauth']);

        return redirect()->route('profile.edit')->with('success', 'Conta Google desvinculada. Continue usando sua senha para entrar.');
    }

    private function provider(): GoogleProvider
    {
        // Instância nova por requisição: não reutiliza usuário/state de outro fluxo.
        return Socialite::buildProvider(GoogleProvider::class, config('services.google'))->enablePKCE();
    }

    private function emailExists(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(['google_registration', 'google_confirmation', 'auth.password_confirmed_at']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->intendedDestination($request));
    }

    private function intendedDestination(Request $request): string
    {
        $target = $request->session()->pull('url.intended');
        $origin = rtrim((string) config('app.url'), '/');
        if (is_string($target) && ! str_contains($target, '\\')
            && (str_starts_with($target, '/') && ! str_starts_with($target, '//')
                || str_starts_with($target, $origin.'/'))) {
            return $target;
        }

        return route('dashboard', absolute: false);
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        $request->session()->forget(['google_oauth', 'google_registration', 'google_confirmation', 'state', 'code_verifier']);

        return redirect()->route($request->user() ? 'profile.edit' : 'login')->withErrors(['google' => $message]);
    }
}
