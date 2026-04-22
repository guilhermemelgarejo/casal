<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Couple extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'invite_code', 'monthly_income', 'spending_alert_threshold'];

    public function billingOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_owner_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function creditCardStatements()
    {
        return $this->hasMany(CreditCardStatement::class);
    }

    public function plannedIncomeVersions()
    {
        return $this->hasMany(CouplePlannedIncome::class);
    }

    public function financialProjects()
    {
        return $this->hasMany(FinancialProject::class);
    }

    public function resolvePlannedMonthlyIncomeForMonth(int $year, int $month): float
    {
        $v = CouplePlannedIncome::amountVigenteForMonth((int) $this->id, $year, $month);
        if ($v !== null) {
            return $v;
        }

        return (float) ($this->monthly_income ?? 0);
    }
}
