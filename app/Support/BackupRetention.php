<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Decide quais backups do Google Drive passaram do prazo de guarda informado na Política de Privacidade.
 */
final class BackupRetention
{
    /** Só arquivos gerados pela rotina (backup_<banco>_<data>.zip) podem ser apagados. */
    public const FILE_PATTERN = '/^backup_[A-Za-z0-9_-]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/';

    /** Mesmo vencidos, os backups mais recentes ficam, para nunca esvaziar a pasta por erro de data. */
    public const KEEP_LATEST = 7;

    public static function days(): ?int
    {
        $days = (int) config('backup.retention_days');

        return $days > 0 ? $days : null;
    }

    /**
     * @param  list<array{id: string, name: string, createdTime: string}>  $files
     * @return list<array{id: string, name: string, createdTime: string}>
     */
    public static function expired(array $files, CarbonInterface $now, int $days, int $keepLatest = self::KEEP_LATEST): array
    {
        $backups = array_values(array_filter(
            $files,
            fn (array $file) => preg_match(self::FILE_PATTERN, $file['name']) === 1
        ));

        usort($backups, fn (array $a, array $b) => strcmp($b['createdTime'], $a['createdTime']));

        $cutoff = CarbonImmutable::instance($now)->subDays($days);

        return array_values(array_filter(
            array_slice($backups, $keepLatest),
            fn (array $file) => CarbonImmutable::parse($file['createdTime'])->lessThan($cutoff)
        ));
    }
}
