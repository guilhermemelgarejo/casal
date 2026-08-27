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
}
