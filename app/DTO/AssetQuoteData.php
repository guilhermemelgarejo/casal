<?php

namespace App\DTO;

class AssetQuoteData
{
    public function __construct(
        public readonly string $assetType,
        public readonly string $assetCode,
        public readonly float $price,
        public readonly ?float $pctChange24h = null,
        public readonly ?float $high24h = null,
        public readonly ?float $low24h = null,
        public readonly ?string $source = null,
        public readonly ?string $timestamp = null,
    ) {
    }

    public function formattedPrice(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function formattedPctChange(): ?string
    {
        if ($this->pctChange24h === null) {
            return null;
        }

        $prefix = $this->pctChange24h >= 0 ? '+' : '';
        return $prefix . number_format($this->pctChange24h, 2, ',', '.') . '%';
    }

    public function toArray(): array
    {
        return [
            'asset_type' => $this->assetType,
            'asset_code' => $this->assetCode,
            'price' => $this->price,
            'formatted_price' => $this->formattedPrice(),
            'pct_change_24h' => $this->pctChange24h,
            'formatted_pct_change' => $this->formattedPctChange(),
            'high_24h' => $this->high24h,
            'low_24h' => $this->low24h,
            'source' => $this->source,
            'timestamp' => $this->timestamp ?? now()->toIso8601String(),
        ];
    }
}
