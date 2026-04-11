<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Billing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

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

    public function isCasalAdmin(): bool
    {
        $adminCoupleId = config('duozen.subscription_admin_couple_id');

        if ($adminCoupleId !== null && (int) $this->couple_id === (int) $adminCoupleId) {
            return true;
        }

        return $this->emailMatchesList(config('duozen.admin_emails', []));
    }

    public function isBillingExempt(): bool
    {
        if ($this->isCasalAdmin()) {
            return true;
        }

        return $this->emailMatchesList(config('duozen.billing_exempt_emails', []));
    }

    /**
     * Casal com acesso: algum membro tem subscrição Stripe válida (inclui período de teste com trial).
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
