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
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'asset_quantity' => 'decimal:8',
            'asset_avg_price' => 'decimal:4',
        ];
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

    public function totalInvestedBrl(): float
    {
        if (! $this->isCustomAsset()) {
            return $this->savedProgress();
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
        if (! $this->isCustomAsset() || $currentQuote === null || $currentQuote <= 0) {
            return 0.0;
        }

        return round(($currentQuote - $this->averageAssetPrice()) * $this->currentAssetQuantity(), 2);
    }

    public function profitOrLossPct(?float $currentQuote = null): ?float
    {
        if (! $this->isCustomAsset() || $currentQuote === null || $currentQuote <= 0 || $this->averageAssetPrice() <= 0.00001) {
            return null;
        }

        return round((($currentQuote / $this->averageAssetPrice()) - 1.0) * 100.0, 2);
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

    /**
     * Progresso: soma despesas ligadas ao projeto − soma receitas ligadas (retiradas).
     */
    public function savedProgress(): float
    {
        $in = (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'expense')
            ->sum('amount');

        $out = (float) Transaction::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'income')
            ->sum('amount');

        $interest = (float) FinancialProjectEntry::query()
            ->where('couple_id', $this->couple_id)
            ->where('financial_project_id', $this->id)
            ->where('type', 'interest')
            ->sum('amount');

        return round(($in - $out) + $interest, 2);
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
