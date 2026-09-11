<?php

namespace App\Contracts;

use App\DTO\AssetQuoteData;

interface AssetQuoteProviderInterface
{
    /**
     * Verifica se o provedor suporta a classe de ativo e código informados.
     */
    public function supports(string $assetType, string $assetCode): bool;

    /**
     * Obtém a cotação ao vivo do ativo em BRL (R$).
     */
    public function fetchQuote(string $assetType, string $assetCode): ?AssetQuoteData;

    /**
     * Obtém o histórico de preços mensais de fechamento do ativo (formato: ['YYYY-MM' => float]).
     *
     * @return array<string, float>
     */
    public function fetchMonthlyHistoricalPrices(string $assetType, string $assetCode, int $monthsLimit = 24): array;
}

