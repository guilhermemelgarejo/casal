<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    protected static function booted(): void
    {
        // Reserved tombstones retain ledger foreign keys but cannot authenticate.
        static::addGlobalScope('activeProfiles', function (Builder $query) {
            $query->where('users.email', 'not like', 'deleted+%@users.duozen.invalid');
        });

        static::deleting(function (User $user) {
            // Também revoga identidades quando a exclusão preserva um perfil anonimizado.
            // A tabela pode ainda não existir: a implantação do SQL é manual.
            if (Schema::hasTable('social_accounts')) {
                $user->socialAccounts()->delete();
            }

            if (DB::table('transactions')->where('user_id', $user->id)->exists()) {
                $user->forceFill([
                    'name' => 'Usuário excluído',
                    'email' => 'deleted+'.Str::uuid().'@users.duozen.invalid',
                    'password' => Str::random(80),
                    'remember_token' => null,
                    'email_verified_at' => null,
                    'couple_id' => null,
                    'stripe_id' => null,
                    'pm_type' => null,
                    'pm_last_four' => null,
                    'trial_ends_at' => null,
                ])->saveQuietly();

                return false;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'couple_id',
    ];

    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function hasLocalPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    /**
     * Primeiro token do nome (espaços), para exibição compacta (ex.: coluna "Registrado por").
     */
    public function firstGivenName(): string
    {
        $n = trim((string) $this->name);
        if ($n === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $n, -1, PREG_SPLIT_NO_EMPTY);

        return $parts[0] ?? '';
    }

    public function isCasalAdmin(): bool
    {
        if ($this->isExistingAdministrator()) {
            return true;
        }

        return $this->hasVerifiedEmail() && (
            $this->emailMatchesList(config('duozen.admin_emails', []))
            || in_array((int) $this->id, config('duozen.admin_user_ids', []), true)
        );
    }

    private function isExistingAdministrator(): bool
    {
        foreach (config('duozen.existing_admin_accounts', []) as $account) {
            if (
                isset($account['id'], $account['email'], $account['created_before'])
                && (int) $this->id === (int) $account['id']
                && strcasecmp((string) $this->email, (string) $account['email']) === 0
                && $this->created_at !== null
                && $this->created_at->lessThan($account['created_before'])
            ) {
                return true;
            }
        }

        return false;
    }

    public function isBillingExempt(): bool
    {
        if ($this->isCasalAdmin()) {
            return true;
        }

        return $this->hasVerifiedEmail()
            && $this->emailMatchesList(config('duozen.billing_exempt_emails', []));
    }

    /**
     * Casal com acesso: algum membro tem assinatura Stripe válida (inclui período de teste com trial).
     */
    public function coupleHasBillingAccess(): bool
    {
        if (! $this->couple_id) {
            return false;
        }

        $this->loadMissing('couple.users');

        return $this->couple->users->contains(fn (User $member) => $member->subscribed('default'));
    }

    /**
     * Alinhado ao middleware couple-billing: pode usar rotas do app (painel, lançamentos, etc.).
     */
    public function passesCoupleBillingGate(): bool
    {
        if (! Billing::isEnforced()) {
            return true;
        }

        if ($this->isBillingExempt()) {
            return true;
        }

        return $this->coupleHasBillingAccess();
    }

    /**
     * @param  list<string>  $emails
     */
    protected function emailMatchesList(array $emails): bool
    {
        if ($emails === []) {
            return false;
        }

        $needle = strtolower($this->email);

        foreach ($emails as $allowed) {
            if (strtolower((string) $allowed) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
