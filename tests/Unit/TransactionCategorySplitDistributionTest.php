<?php

namespace Tests\Unit;

use App\Support\TransactionCategorySplitDistribution;
use PHPUnit\Framework\TestCase;

class TransactionCategorySplitDistributionTest extends TestCase
{
    public function test_distribui_tres_parcelas_mantendo_totais_por_categoria(): void
    {
        $total = 100_00;
        $ordered = [
            ['category_id' => 1, 'cents' => 60_00],
            ['category_id' => 2, 'cents' => 40_00],
        ];
        $parcels = [33_33, 33_33, 33_34];

        $out = TransactionCategorySplitDistribution::perParcel($total, $ordered, $parcels);

        $this->assertCount(3, $out);
        $sumA = 0;
        $sumB = 0;
        foreach ($out as $idx => $lines) {
            $this->assertCount(2, $lines);
            $this->assertSame($parcels[$idx], array_sum(array_column($lines, 'cents')));
            $sumA += $lines[0]['cents'];
            $sumB += $lines[1]['cents'];
        }
        $this->assertSame(60_00, $sumA);
        $this->assertSame(40_00, $sumB);
    }
}
