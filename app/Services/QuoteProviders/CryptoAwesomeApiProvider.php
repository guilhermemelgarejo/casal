<?php

namespace App\Services\QuoteProviders;

use App\Contracts\AssetQuoteProviderInterface;
use App\DTO\AssetQuoteData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CryptoAwesomeApiProvider implements AssetQuoteProviderInterface
{
    /**
     * Suporta criptomoedas como BTC, ETH, SOL, etc.
     */
    public function supports(string $assetType, string $assetCode): bool
    {
        return $assetType === 'crypto' || in_array(strtoupper($assetCode), ['BTC', 'BITCOIN', 'ETH', 'SOL', 'USDT'], true);
    }

    public function fetchQuote(string $assetType, string $assetCode): ?AssetQuoteData
    {
        $normalizedCode = strtoupper(trim($assetCode));
        if ($normalizedCode === 'BITCOIN') {
            $normalizedCode = 'BTC';
        }

        // 1. Prioridade: Binance API (cotação spot ao vivo em tempo real a cada segundo)
        $binanceQuote = $this->fetchFromBinance($normalizedCode);
        if ($binanceQuote !== null) {
            return $binanceQuote;
        }

        // 2. Fallback: AwesomeAPI (padrão Brasil)
        $awesomeQuote = $this->fetchFromAwesomeApi($normalizedCode);
        if ($awesomeQuote !== null) {
            return $awesomeQuote;
        }

        // 3. Fallback: CoinGecko API
        return $this->fetchFromCoinGecko($normalizedCode);
    }

    private function fetchFromAwesomeApi(string $code): ?AssetQuoteData
    {
        try {
            $pair = $code . '-BRL';
            $response = Http::timeout(4)->get("https://economia.awesomeapi.com.br/last/{$pair}");

            if ($response->successful()) {
                $key = $code . 'BRL';
                $data = $response->json($key);

                if (is_array($data) && isset($data['bid'])) {
                    $price = (float) $data['bid'];
                    $pctChange = isset($data['pctChange']) ? (float) $data['pctChange'] : null;
                    $high = isset($data['high']) ? (float) $data['high'] : null;
                    $low = isset($data['low']) ? (float) $data['low'] : null;

                    return new AssetQuoteData(
                        assetType: 'crypto',
                        assetCode: $code,
                        price: $price,
                        pctChange24h: $pctChange,
                        high24h: $high,
                        low24h: $low,
                        source: 'AwesomeAPI',
                        timestamp: now()->toIso8601String()
                    );
                }
            }
        } catch (Throwable $e) {
            Log::debug("CryptoAwesomeApiProvider: Falha ao consultar AwesomeAPI para {$code}: " . $e->getMessage());
        }

        return null;
    }

    private function fetchFromBinance(string $code): ?AssetQuoteData
    {
        try {
            $symbol = $code . 'BRL';
            $response = Http::timeout(4)->get("https://api.binance.com/api/v3/ticker/24hr", [
                'symbol' => $symbol,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && isset($data['lastPrice'])) {
                    $price = (float) $data['lastPrice'];
                    $pctChange = isset($data['priceChangePercent']) ? (float) $data['priceChangePercent'] : null;
                    $high = isset($data['highPrice']) ? (float) $data['highPrice'] : null;
                    $low = isset($data['lowPrice']) ? (float) $data['lowPrice'] : null;

                    return new AssetQuoteData(
                        assetType: 'crypto',
                        assetCode: $code,
                        price: $price,
                        pctChange24h: $pctChange,
                        high24h: $high,
                        low24h: $low,
                        source: 'Binance',
                        timestamp: now()->toIso8601String()
                    );
                }
            }
        } catch (Throwable $e) {
            Log::debug("CryptoAwesomeApiProvider: Falha ao consultar Binance para {$code}: " . $e->getMessage());
        }

        return null;
    }

    private function fetchFromCoinGecko(string $code): ?AssetQuoteData
    {
        try {
            $coinMap = [
                'BTC' => 'bitcoin',
                'ETH' => 'ethereum',
                'SOL' => 'solana',
                'USDT' => 'tether',
            ];

            $coinId = $coinMap[$code] ?? strtolower($code);
            $response = Http::timeout(4)->get("https://api.coingecko.com/api/v3/simple/price", [
                'ids' => $coinId,
                'vs_currencies' => 'brl',
                'include_24hr_change' => 'true',
            ]);

            if ($response->successful()) {
                $data = $response->json($coinId);
                if (is_array($data) && isset($data['brl'])) {
                    $price = (float) $data['brl'];
                    $pctChange = isset($data['brl_24h_change']) ? (float) $data['brl_24h_change'] : null;

                    return new AssetQuoteData(
                        assetType: 'crypto',
                        assetCode: $code,
                        price: $price,
                        pctChange24h: $pctChange,
                        source: 'CoinGecko',
                        timestamp: now()->toIso8601String()
                    );
                }
            }
        } catch (Throwable $e) {
            Log::debug("CryptoAwesomeApiProvider: Falha ao consultar CoinGecko para {$code}: " . $e->getMessage());
        }

        return null;
    }
}
