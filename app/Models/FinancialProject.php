<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialProject extends Model
{
    public const ASSET_TYPE_FIAT = 'fiat';
    public const ASSET_TYPE_CRYPTO = 'crypto';
    public const ASSET_TYPE_STOCK = 'stock';
    public const ASSET_TYPE_FII = 'fii';
    public const ASSET_TYPE_FIXED_INCOME = 'fixed_income';
    public const ASSET_TYPE_OTHER = 'other';

    protected $fillable = [
        'couple_id',
        'name',
        'asset_type',
        'asset_code',
        'asset_quantity',
        'asset_avg_price',
        'target_amount',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'asset_quantity' => 'decimal:8',
            'asset_avg_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'financial_project_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinancialProjectEntry::class, 'financial_project_id');
    }

    public function isCustomAsset(): bool
    {
        return ($this->asset_type ?? self::ASSET_TYPE_FIAT) !== self::ASSET_TYPE_FIAT;
    }

    public function isBitcoin(): bool
    {
        $type = strtolower((string) ($this->asset_type ?? ''));
        $code = strtoupper((string) ($this->asset_code ?? ''));

        return $type === self::ASSET_TYPE_CRYPTO && in_array($code, ['BTC', 'BITCOIN'], true);
    }

    public function assetTypeLabel(): string
    {
        return match ($this->asset_type ?? self::ASSET_TYPE_FIAT) {
            self::ASSET_TYPE_CRYPTO => 'Criptomoeda',
            self::ASSET_TYPE_STOCK => 'Ação / ETF',
            self::ASSET_TYPE_FII => 'FII',
            self::ASSET_TYPE_FIXED_INCOME => 'Renda Fixa / Tesouro',
            self::ASSET_TYPE_OTHER => 'Outro Ativo',
            default => 'Moeda (R$)',
        };
    }

    public function assetUnitLabel(): string
    {
        if (! $this->isCustomAsset()) {
            return 'R$';
        }

        if ($this->isBitcoin()) {
            return 'BTC';
        }

        return match ($this->asset_type ?? self::ASSET_TYPE_FIAT) {
            self::ASSET_TYPE_CRYPTO => strtoupper((string) ($this->asset_code ?: 'UNID')),
            self::ASSET_TYPE_STOCK => 'Ações',
            self::ASSET_TYPE_FII => 'Cotas',
            self::ASSET_TYPE_FIXED_INCOME => 'Títulos',
            default => 'Unidades',
        };
    }

    public function currentAssetQuantity(): float
    {
        return (float) ($this->asset_quantity ?? 0);
    }

    public function averageAssetPrice(): float
    {
        return (float) ($this->asset_avg_price ?? 0);
    }

    /**
     * Calcula as métricas de saldo, capital investido e rendimento real para cofrinhos fiat,
     * considerando a ordem cronológica de aportes, retiradas e juros, e tratando
     * ajustes iniciais como saldo inicial.
     *
     * @return array{saved: float, principal: float, profit: float, profit_pct: float}
     */
    public function fiatProfitMetrics(): array
    {
        $txs = $this->relationLoaded('transactions')
            ? $this->transactions
            : $this->transactions()->get();

        $entries = $this->relationLoaded('entries')
            ? $this->entries
            : $this->entries()->get();

        $movements = collect();

        foreach ($txs as $t) {
            $dateStr = $t->date instanceof \Carbon\Carbon ? $t->date->format('Y-m-d') : (string) $t->date;
            $movements->push([
                'date' => $dateStr,
                'id' => (int) $t->id,
                'amount' => (float) $t->amount,
                'type' => $t->type === 'expense' ? 'aporte' : 'retirada',
            ]);
        }

        foreach ($entries as $e) {
            $dateStr = $e->date instanceof \Carbon\Carbon ? $e->date->format('Y-m-d') : (string) $e->date;
            $isAjuste = trim(strtolower((string) ($e->note ?? ''))) === 'ajuste';
            $movements->push([
                'date' => $dateStr,
                'id' => (int) $e->id,
                'amount' => (float) $e->amount,
                'type' => $isAjuste ? 'ajuste_saldo' : 'juros',
            ]);
        }

        $sorted = $movements->sortBy(function (array $m): string {
            return $m['date'] . '_' . sprintf('%08d', $m['id']);
        })->values();

        $balance = 0.0;
        $principal = 0.0;
        $accumulatedInterest = 0.0;

        foreach ($sorted as $m) {
            if ($m['type'] === 'aporte' || $m['type'] === 'ajuste_saldo') {
                $principal += $m['amount'];
                $balance += $m['amount'];
            } elseif ($m['type'] === 'juros') {
                $accumulatedInterest += $m['amount'];
                $balance += $m['amount'];
            } elseif ($m['type'] === 'retirada') {
                $withdrawal = $m['amount'];
                $balance = max(0.0, $balance - $withdrawal);

                if ($balance <= 0.0001) {
                    $principal = 0.0;
                    $accumulatedInterest = 0.0;
                    $balance = 0.0;
                } else {
                    if ($withdrawal >= $principal) {
                        $accumulatedInterest = max(0.0, $accumulatedInterest - ($withdrawal - $principal));
                        $principal = 0.0;
                    } else {
                        $principal = max(0.0, $principal - $withdrawal);
                    }
                    $accumulatedInterest = max(0.0, round($balance - $principal, 2));
                }
            }
        }

        $profit = round($balance - $principal, 2);
        $profitPct = $principal > 0.0001 ? round(($profit / $principal) * 100.0, 2) : 0.0;

        return [
            'saved' => round($balance, 2),
            'principal' => round($principal, 2),
            'profit' => $profit,
            'profit_pct' => $profitPct,
        ];
    }

    public function totalInvestedBrl(): float
    {
        if (! $this->isCustomAsset()) {
            return $this->fiatProfitMetrics()['principal'];
        }

        return round($this->currentAssetQuantity() * $this->averageAssetPrice(), 2);
    }

    public function currentEstimatedValue(?float $currentQuote = null): float
    {
        if (! $this->isCustomAsset()) {
            return $this->savedProgress();
        }

        if ($currentQuote === null || $currentQuote <= 0) {
            return $this->totalInvestedBrl();
        }

        return round($this->currentAssetQuantity() * $currentQuote, 2);
    }

    public function profitOrLoss(?float $currentQuote = null): float
    {
        if ($this->isCustomAsset()) {
            if ($currentQuote === null || $currentQuote <= 0) {
                return 0.0;
            }

            return round(($currentQuote - $this->averageAssetPrice()) * $this->currentAssetQuantity(), 2);
        }

        return $this->fiatProfitMetrics()['profit'];
    }

    public function profitOrLossPct(?float $currentQuote = null): ?float
    {
        if ($this->isCustomAsset()) {
            if ($currentQuote === null || $currentQuote <= 0 || $this->averageAssetPrice() <= 0.00001) {
                return null;
            }

            return round((($currentQuote / $this->averageAssetPrice()) - 1.0) * 100.0, 2);
        }

        return $this->fiatProfitMetrics()['profit_pct'];
    }

    /**
     * Recalcula o Preço Médio ponderado a partir de um novo aporte.
     *
     * @return array{new_quantity: float, new_avg_price: float, total_invested: float, previous_quantity: float, previous_avg_price: float}
     */
    public function recalculateAveragePriceOnAporte(float $brlAmount, float $assetQuantity, ?float $unitPrice = null): array
    {
        $q0 = (float) ($this->asset_quantity ?? 0);
        $pm0 = (float) ($this->asset_avg_price ?? 0);

        if ($q0 <= 0.00000001 || $pm0 <= 0.0001) {
            $newQuantity = $assetQuantity;
            $newAvgPrice = $unitPrice !== null && $unitPrice > 0
                ? $unitPrice
                : ($assetQuantity > 0 ? ($brlAmount / $assetQuantity) : 0.0);
            $newTotalInvested = $brlAmount;
        } else {
            $v0 = $q0 * $pm0;
            $newTotalInvested = $v0 + $brlAmount;
            $newQuantity = $q0 + $assetQuantity;
            $newAvgPrice = $newQuantity > 0 ? ($newTotalInvested / $newQuantity) : 0.0;
        }

        $this->asset_quantity = number_format($newQuantity, 8, '.', '');
        $this->asset_avg_price = number_format($newAvgPrice, 4, '.', '');
        $this->save();

        return [
            'new_quantity' => (float) $this->asset_quantity,
            'new_avg_price' => (float) $this->asset_avg_price,
            'total_invested' => round($newTotalInvested, 2),
            'previous_quantity' => $q0,
            'previous_avg_price' => $pm0,
        ];
    }

    /**
     * Registra uma retirada do ativo mantendo o preço médio inalterado.
     */
    public function registerWithdrawal(float $assetQuantity): array
    {
        $q0 = (float) ($this->asset_quantity ?? 0);
        $newQuantity = max(0.0, $q0 - $assetQuantity);

        $this->asset_quantity = number_format($newQuantity, 8, '.', '');
        $this->save();

        return [
            'new_quantity' => (float) $this->asset_quantity,
            'avg_price' => (float) ($this->asset_avg_price ?? 0),
            'previous_quantity' => $q0,
        ];
    }

    public function totalDeposits(): float
    {
        return (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function totalWithdrawals(): float
    {
        return (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'income')
            ->sum('amount');
    }

    public function totalInterest(): float
    {
        return (float) FinancialProjectEntry::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', FinancialProjectEntry::TYPE_INTEREST)
            ->sum('amount');
    }

    public function netDeposited(): float
    {
        return round($this->totalDeposits() - $this->totalWithdrawals(), 2);
    }

    /**
     * Progresso: soma despesas ligadas ao projeto − soma receitas ligadas (retiradas) + juros.
     */
    public function savedProgress(): float
    {
        return round($this->netDeposited() + $this->totalInterest(), 2);
    }

    public function remainingToTarget(): ?float
    {
        $target = $this->target_amount !== null ? (float) $this->target_amount : null;
        if ($target === null) {
            return null;
        }

        $current = $this->isCustomAsset() ? $this->totalInvestedBrl() : $this->savedProgress();
        return max(0.0, round($target - $current, 2));
    }

    public function progressPct(): ?float
    {
        $target = $this->target_amount !== null ? (float) $this->target_amount : null;
        if ($target === null || $target < 0.00001) {
            return null;
        }

        $current = $this->isCustomAsset() ? $this->totalInvestedBrl() : $this->savedProgress();
        return min(100.0, round(($current / $target) * 100.0, 2));
    }
}
