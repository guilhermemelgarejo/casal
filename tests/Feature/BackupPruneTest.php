<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabaseToDrive;
use Carbon\Carbon;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\FileList;
use Google\Service\Drive\Resource\Files;
use Illuminate\Console\OutputStyle;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class BackupPruneTest extends TestCase
{
    private function command(GoogleDriveService $service): BackupDatabaseToDrive
    {
        $command = new class($service) extends BackupDatabaseToDrive
        {
            public function __construct(private GoogleDriveService $fakeService)
            {
                parent::__construct();
            }

            protected function createGoogleDriveService(): GoogleDriveService
            {
                return $this->fakeService;
            }

            public function prune(string $folderId): void
            {
                $this->pruneExpiredBackups($folderId);
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        return $command;
    }

    private function driveWith(array $files, Files $resource): GoogleDriveService
    {
        $list = new FileList;
        $list->setFiles(array_map(function (array $f) {
            $file = new DriveFile;
            $file->setId($f[0]);
            $file->setName($f[1]);
            $file->setCreatedTime($f[2]);

            return $file;
        }, $files));

        $resource->shouldReceive('listFiles')->once()->andReturn($list);
        $service = Mockery::mock(GoogleDriveService::class);
        $service->files = $resource;

        return $service;
    }

    public function test_apaga_do_drive_os_backups_vencidos_e_mantem_os_demais(): void
    {
        Carbon::setTestNow('2026-10-24 03:00:00');
        config(['backup.retention_days' => 30]);

        $files = [];
        for ($day = 0; $day < 40; $day++) {
            $date = Carbon::now()->subDays($day);
            $files[] = ['id'.$day, 'backup_duozen_'.$date->format('Y-m-d_H-i-s').'.zip', $date->toIso8601ZuluString()];
        }
        $files[] = ['manual', 'planilha-pessoal.zip', '2020-01-01T00:00:00Z'];

        $deleted = [];
        $resource = Mockery::mock(Files::class);
        $resource->shouldReceive('delete')->andReturnUsing(function (string $id) use (&$deleted) {
            $deleted[] = $id;
        });

        $this->command($this->driveWith($files, $resource))->prune('pasta');

        sort($deleted);
        $expected = array_map(fn (int $day) => 'id'.$day, range(31, 39));
        sort($expected);
        $this->assertSame($expected, $deleted);
        Carbon::setTestNow();
    }

    public function test_sem_prazo_configurado_nada_e_apagado(): void
    {
        config(['backup.retention_days' => 0]);
        $resource = Mockery::mock(Files::class);
        $resource->shouldNotReceive('listFiles');
        $resource->shouldNotReceive('delete');
        $service = Mockery::mock(GoogleDriveService::class);
        $service->files = $resource;

        $this->command($service)->prune('pasta');

        $this->addToAssertionCount(1);
    }
}
