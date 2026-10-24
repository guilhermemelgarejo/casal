<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

class DeployExtractorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/duozen-deploy-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/storage/framework', 0777, true);
        mkdir($this->directory.'/vendor', 0777, true);
        mkdir($this->directory.'/bootstrap', 0777, true);
        file_put_contents($this->directory.'/.env', "DEPLOY_TOKEN=fixture-token\nDB_DATABASE=preserve-existing-database\n");
        file_put_contents($this->directory.'/financial-data.sentinel', 'preserve financial data');
        copy(dirname(__DIR__, 2).'/deploy_extractor.php', $this->directory.'/deploy_extractor.php');

        // Standalone fakes: these tests never bootstrap the real app or connect
        // to any database, including the existing local database.
        file_put_contents($this->directory.'/bootstrap/app.php', <<<'PHP'
<?php
return new class {
    public function make($contract) {
        return new class { public function bootstrap() {} };
    }
};
PHP);
        file_put_contents($this->directory.'/vendor/autoload.php', <<<'PHP'
<?php
namespace Illuminate\Support\Facades;
class Artisan {
    public static function call($command) {
        $allowed = ['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'];
        if (!in_array($command, $allowed, true)) { throw new \RuntimeException('Unsafe command'); }
        file_put_contents(__DIR__.'/commands.log', $command."\n", FILE_APPEND);
        if (getenv('FIXTURE_CACHE_FAILURE') && $command === 'route:cache') {
            throw new \RuntimeException('PRIVATE_DEPLOY_DETAILS');
        }
        return 0;
    }
}
PHP);
    }

    protected function tearDown(): void
    {
        $target = realpath($this->directory);
        if ($target && dirname($target) === realpath(sys_get_temp_dir()) && str_starts_with(basename($target), 'duozen-deploy-test-')) {
            (new Filesystem)->deleteDirectory($target);
        }
        parent::tearDown();
    }

    private function package(): string
    {
        $zip = new ZipArchive;
        $zip->open($this->directory.'/release.zip', ZipArchive::CREATE);
        $zip->addFromString('app/release-marker.txt', 'verified release');
        $zip->close();

        return hash_file('sha256', $this->directory.'/release.zip');
    }

    private function request(string $hash = '', string $token = 'fixture-token', string $method = 'POST', bool $cacheFailure = false, bool $disableSymlinks = false): array
    {
        $script = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = getenv('FIXTURE_METHOD');
$_SERVER['HTTP_X_DEPLOY_TOKEN'] = getenv('FIXTURE_TOKEN');
$_SERVER['HTTP_X_RELEASE_SHA256'] = getenv('FIXTURE_HASH');
register_shutdown_function(function () { file_put_contents('response-status.txt', (string) http_response_code()); });
require 'deploy_extractor.php';
PHP;
        $arguments = [PHP_BINARY];
        if ($disableSymlinks) {
            array_push($arguments, '-d', 'disable_functions=symlink');
        }
        array_push($arguments, '-r', $script);
        $process = new Process($arguments, $this->directory, [
            'FIXTURE_METHOD' => $method,
            'FIXTURE_TOKEN' => $token,
            'FIXTURE_HASH' => $hash,
            'FIXTURE_CACHE_FAILURE' => $cacheFailure ? '1' : '',
        ]);
        $process->mustRun();

        return [
            'status' => (int) file_get_contents($this->directory.'/response-status.txt'),
            'body' => $process->getOutput(),
            'json' => json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    public function test_invalid_token_cannot_extract_anything(): void
    {
        $hash = $this->package();
        $response = $this->request($hash, 'invalid-token');
        $this->assertSame(403, $response['status']);
        $this->assertFileDoesNotExist($this->directory.'/app/release-marker.txt');
    }

    public function test_missing_hash_cannot_extract_or_clear_caches(): void
    {
        $this->package();
        $response = $this->request();
        $this->assertSame(400, $response['status']);
        $this->assertFileDoesNotExist($this->directory.'/app/release-marker.txt');
        $this->assertFileDoesNotExist($this->directory.'/vendor/commands.log');
    }

    public function test_mismatched_hash_rejects_package_before_changing_files(): void
    {
        $this->package();
        $response = $this->request(str_repeat('0', 64));
        $this->assertSame(422, $response['status']);
        $this->assertFileExists($this->directory.'/release.zip');
        $this->assertFileDoesNotExist($this->directory.'/app/release-marker.txt');
        $this->assertFileDoesNotExist($this->directory.'/vendor/commands.log');
    }

    public function test_missing_package_is_not_reported_as_success(): void
    {
        $response = $this->request(str_repeat('0', 64));
        $this->assertSame(409, $response['status']);
        $this->assertSame('error', $response['json']['status']);
    }

    public function test_corrupted_archive_with_matching_digest_cannot_clear_caches(): void
    {
        file_put_contents($this->directory.'/release.zip', 'not a zip');
        $response = $this->request(hash_file('sha256', $this->directory.'/release.zip'));
        $this->assertSame(500, $response['status']);
        $this->assertSame('error', $response['json']['status']);
        $this->assertFileDoesNotExist($this->directory.'/vendor/commands.log');
    }

    public function test_verified_package_preserves_environment_and_data_and_only_runs_cache_commands(): void
    {
        $hash = $this->package();
        $environment = file_get_contents($this->directory.'/.env');
        $response = $this->request($hash);
        $this->assertSame(200, $response['status']);
        $this->assertSame('success', $response['json']['status']);
        $this->assertSame('verified release', file_get_contents($this->directory.'/app/release-marker.txt'));
        $this->assertSame($environment, file_get_contents($this->directory.'/.env'));
        $this->assertSame('preserve financial data', file_get_contents($this->directory.'/financial-data.sentinel'));
        $this->assertSame(
            ['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled', 'config:cache', 'route:cache', 'view:cache'],
            file($this->directory.'/vendor/commands.log', FILE_IGNORE_NEW_LINES),
        );
    }

    public function test_cache_error_identifies_command_without_leaking_private_exception(): void
    {
        $response = $this->request($this->package(), cacheFailure: true);
        $this->assertSame(500, $response['status']);
        $this->assertSame('route:cache', $response['json']['logs']['cache_error']['command']);
        $this->assertArrayHasKey('reference', $response['json']['logs']['cache_error']);
        $this->assertStringNotContainsString('PRIVATE_DEPLOY_DETAILS', $response['body']);
    }

    public function test_disabled_symlink_does_not_fail_an_otherwise_verified_deploy(): void
    {
        mkdir($this->directory.'/storage/app/public', 0777, true);
        file_put_contents($this->directory.'/storage/app/public/user-upload.txt', 'preserve upload');
        $response = $this->request($this->package(), disableSymlinks: true);

        $this->assertSame(200, $response['status']);
        $this->assertSame('success', $response['json']['status']);
        $this->assertArrayHasKey('storage_link_warning', $response['json']['logs']);
        $this->assertArrayNotHasKey('storage_link_error', $response['json']['logs']);
        $this->assertSame('preserve upload', file_get_contents($this->directory.'/storage/app/public/user-upload.txt'));
        $this->assertFileDoesNotExist($this->directory.'/public/storage/user-upload.txt');
    }
}
