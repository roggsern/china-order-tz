<?php

namespace Tests\Unit\Ops;

use App\Support\Ops\Backup\BackupDependencyChecker;
use App\Support\Ops\Backup\BackupDependencyGate;
use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupStorageManager;
use App\Support\Ops\Backup\LocalBackupStorage;
use App\Support\Ops\Backup\S3CompatibleBackupStorage;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 4A — backup foundation.
 */
class BackupFoundationTest extends TestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = storage_path('framework/testing/backup-foundation-'.uniqid('', true));
        config([
            'backup.enabled' => true,
            'backup.root' => $this->backupRoot,
            'backup.database.driver' => 'fake',
            'backup.destination.driver' => 'local',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupRoot)) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_backup_configuration_loads_required_keys(): void
    {
        $this->assertTrue((bool) config('backup.enabled'));
        $this->assertNotSame('', (string) config('backup.root'));
        $this->assertGreaterThan(0, (int) config('backup.retention.daily'));
        $this->assertGreaterThan(0, (int) config('backup.retention.weekly'));
        $this->assertGreaterThan(0, (int) config('backup.retention.monthly'));
        $this->assertContains('app/public', config('backup.media.paths'));
        $this->assertContains('app/private', config('backup.media.paths'));
        $this->assertTrue((bool) config('backup.verification.enabled'));
        $this->assertSame('local', config('backup.destination.driver'));
        $this->assertSame('DISABLED', config('backup.database.ssl_mode'));
        $this->assertTrue((bool) config('backup.database.connectivity_check'));
    }

    public function test_dependency_gate_resolves_to_checker(): void
    {
        $gate = app(BackupDependencyGate::class);
        $this->assertInstanceOf(BackupDependencyChecker::class, $gate);
    }

    public function test_dependency_check_passes_when_dependencies_ok(): void
    {
        $result = app(BackupDependencyChecker::class)->check(requireMysqldump: false);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['checks']['mysqldump']);
        $this->assertTrue($result['checks']['database_connectivity']);
        $this->assertTrue($result['checks']['backup_root_writable']);
        $this->assertTrue($result['checks']['media_source_readable']);
        $this->assertTrue($result['checks']['destination_config']);
    }

    public function test_dependency_check_succeeds_when_backups_disabled(): void
    {
        config(['backup.enabled' => false]);

        $result = app(BackupDependencyChecker::class)->check(requireMysqldump: true);

        $this->assertTrue($result['ok']);
        $this->assertContains('Backups disabled.', $result['messages']);
    }

    public function test_dependency_check_fails_safely_when_mysqldump_missing(): void
    {
        config(['backup.database.mysqldump_bin' => '/nonexistent/mysqldump-rc1-backup']);

        $result = app(BackupDependencyChecker::class)->check(requireMysqldump: true);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['checks']['mysqldump']);
        $this->assertNotEmpty($result['messages']);

        foreach ($result['messages'] as $message) {
            $this->assertStringNotContainsStringIgnoringCase('password', $message);
            $this->assertStringNotContainsStringIgnoringCase('secret', $message);
        }
    }

    public function test_assert_ready_throws_when_mysqldump_missing(): void
    {
        config(['backup.database.mysqldump_bin' => '/nonexistent/mysqldump-rc1-backup']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mysqldump');

        app(BackupDependencyChecker::class)->assertReady(requireMysqldump: true);
    }

    public function test_local_destination_driver_validates_and_put_is_noop(): void
    {
        $manager = app(BackupStorageManager::class);
        $driver = $manager->driver('local');

        $this->assertInstanceOf(LocalBackupStorage::class, $driver);
        $this->assertSame('local', $driver->driverName());
        $driver->validateConfig();

        app(BackupPaths::class)->ensureLayout();
        $file = $this->backupRoot.DIRECTORY_SEPARATOR.'daily'.DIRECTORY_SEPARATOR.'probe.txt';
        File::ensureDirectoryExists(dirname($file));
        File::put($file, 'ok');
        $driver->put($file, 'daily/probe.txt');
    }

    public function test_s3_destination_requires_configuration_and_hides_secrets_on_put_failure(): void
    {
        config([
            'backup.destination.driver' => 's3',
            'backup.destination.s3' => [
                'key' => '',
                'secret' => '',
                'bucket' => '',
                'region' => 'us-east-1',
                'endpoint' => null,
                'path_prefix' => 'china-order-tz',
                'use_path_style' => true,
            ],
        ]);

        $driver = app(BackupStorageManager::class)->driver('s3');
        $this->assertInstanceOf(S3CompatibleBackupStorage::class, $driver);

        $this->expectException(RuntimeException::class);
        $driver->validateConfig();
    }

    public function test_s3_put_fails_without_sdk_without_leaking_secrets(): void
    {
        if (class_exists(\Aws\S3\S3Client::class)) {
            $this->markTestSkipped('AWS SDK present; foundation put path uses live client.');
        }

        config([
            'backup.destination.driver' => 's3',
            'backup.destination.s3' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
                'bucket' => 'test-bucket',
                'region' => 'us-east-1',
                'endpoint' => 'http://127.0.0.1:9000',
                'path_prefix' => 'china-order-tz',
                'use_path_style' => true,
            ],
        ]);

        $driver = app(S3CompatibleBackupStorage::class);
        $driver->validateConfig();

        app(BackupPaths::class)->ensureLayout();
        $file = $this->backupRoot.DIRECTORY_SEPARATOR.'daily'.DIRECTORY_SEPARATOR.'x.sql.gz';
        File::ensureDirectoryExists(dirname($file));
        File::put($file, 'gz');

        try {
            $driver->put($file, 'daily/x.sql.gz');
            $this->fail('Expected RuntimeException when AWS SDK is missing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AWS SDK', $e->getMessage());
            $this->assertStringNotContainsString('test-secret', $e->getMessage());
            $this->assertStringNotContainsString('test-key', $e->getMessage());
        }
    }
}
