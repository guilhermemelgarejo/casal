<?php

namespace App\Support;

/**
 * Reparte valores por categoria em cada parcela mantendo as proporções do total
 * e garantindo que a soma por parcela e por categoria (no total) bate com os centavos esperados.
 */
class TransactionCategorySplitDistribution
{
    /**
     * @param  array<int, array{category_id: int, cents: int}>  $orderedAllocations
     * @param  array<int, int>  $parcelCents
     * @return array<int, array<int, array{category_id: int, cents: int}>>
     */
    public static function perParcel(int $totalCents, array $orderedAllocations, array $parcelCents): array
    {
        if ($totalCents <= 0 || $orderedAllocations === []) {
            return [];
        }

        $sumAlloc = 0;
        foreach ($orderedAllocations as $pair) {
            $sumAlloc += $pair['cents'];
        }
        if ($sumAlloc !== $totalCents) {
            throw new \InvalidArgumentException('A soma das parcelas por categoria não confere com o total.');
        }

        $sPrev = 0;
        $out = [];
        $lastIdx = count($orderedAllocations) - 1;

        foreach ($parcelCents as $p) {
            if ($p < 0) {
                throw new \InvalidArgumentException('Valor de parcela inválido.');
            }
            $sCurr = $sPrev + $p;
            $line = [];
            foreach ($orderedAllocations as $idx => $pair) {
                $cK = $pair['cents'];
                if ($idx < $lastIdx) {
                    $alloc = intdiv($sCurr * $cK, $totalCents) - intdiv($sPrev * $cK, $totalCents);
                } else {
                    $alloc = $p - array_sum(array_column($line, 'cents'));
                }
                $line[] = [
                    'category_id' => $pair['category_id'],
                    'cents' => $alloc,
                ];
            }
            $out[] = $line;
            $sPrev = $sCurr;
        }

        return $out;
    }
}
