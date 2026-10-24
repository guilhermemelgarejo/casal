<?php

namespace Tests\Unit;

use App\Support\BackupRetention;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BackupRetentionTest extends TestCase
{
    private function backup(string $date, string $id): array
    {
        $stamp = CarbonImmutable::parse($date);

        return [
            'id' => $id,
            'name' => 'backup_duozen_'.$stamp->format('Y-m-d_H-i-s').'.zip',
            'createdTime' => $stamp->toIso8601ZuluString(),
        ];
    }

    public function test_apaga_apenas_backups_da_rotina_mais_antigos_que_o_prazo(): void
    {
        $now = CarbonImmutable::parse('2026-10-24 03:00:00');
        $files = [];
        for ($day = 0; $day < 120; $day++) {
            $files[] = $this->backup($now->subDays($day)->toDateTimeString(), 'd'.$day);
        }
        $files[] = ['id' => 'manual', 'name' => 'contrato-importante.zip', 'createdTime' => '2020-01-01T00:00:00Z'];

        $expired = BackupRetention::expired($files, $now, 90);
        $ids = array_column($expired, 'id');

        $this->assertCount(29, $expired);
        $this->assertContains('d91', $ids);
        $this->assertContains('d119', $ids);
        $this->assertNotContains('d90', $ids);
        $this->assertNotContains('manual', $ids);
    }

    public function test_mantem_os_mais_recentes_mesmo_vencidos(): void
    {
        $now = CarbonImmutable::parse('2026-10-24 03:00:00');
        $files = [];
        for ($i = 0; $i < 10; $i++) {
            $files[] = $this->backup($now->subDays(200 + $i)->toDateTimeString(), 'old'.$i);
        }

        $expired = BackupRetention::expired($files, $now, 90);

        $this->assertSame(['old7', 'old8', 'old9'], array_column($expired, 'id'));
    }
}
