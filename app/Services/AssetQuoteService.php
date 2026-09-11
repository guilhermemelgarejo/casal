<?php

namespace App\Services;

use App\Contracts\AssetQuoteProviderInterface;
use App\DTO\AssetQuoteData;
use App\Services\QuoteProviders\CryptoAwesomeApiProvider;
use Illuminate\Support\Facades\Cache;

class AssetQuoteService
{
    /**
     * @var array<int, AssetQuoteProviderInterface>
     */
    private array $providers = [];

    public function __construct()
    {
        // Registra provedores padrão
        $this->registerProvider(new CryptoAwesomeApiProvider());
    }

    public function registerProvider(AssetQuoteProviderInterface $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Obtém a cotação de um ativo com cache de 10 segundos.
     */
    public function getQuote(string $assetType, string $assetCode, bool $fresh = false): ?AssetQuoteData
    {
        $cleanType = strtolower(trim($assetType));
        $cleanCode = strtoupper(trim($assetCode));

        if ($cleanType === 'fiat' || empty($cleanCode)) {
            return null;
        }

        $cacheKey = "asset_quote_{$cleanType}_{$cleanCode}";

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(10), function () use ($cleanType, $cleanCode) {
            foreach ($this->providers as $provider) {
                if ($provider->supports($cleanType, $cleanCode)) {
                    $quote = $provider->fetchQuote($cleanType, $cleanCode);
                    if ($quote !== null) {
                        return $quote;
                    }
                }
            }

            return null;
        });
    }

    /**
     * Obtém o mapa de preços históricos mensais de fechamento ('YYYY-MM' => float).
     * Cache de 6 horas para poupar requisições e garantir respostas instantâneas.
     *
     * @return array<string, float>
     */
    public function getMonthlyHistoricalPrices(string $assetType, string $assetCode, bool $fresh = false): array
    {
        $cleanType = strtolower(trim($assetType));
        $cleanCode = strtoupper(trim($assetCode));

        if ($cleanType === 'fiat' || empty($cleanCode)) {
            return [];
        }

        $cacheKey = "asset_monthly_prices_{$cleanType}_{$cleanCode}";

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($cleanType, $cleanCode) {
            foreach ($this->providers as $provider) {
                if ($provider->supports($cleanType, $cleanCode)) {
                    $prices = $provider->fetchMonthlyHistoricalPrices($cleanType, $cleanCode);
                    if (! empty($prices)) {
                        return $prices;
                    }
                }
            }

            return [];
        });
    }
}

