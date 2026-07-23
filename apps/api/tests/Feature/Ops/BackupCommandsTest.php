<?php

namespace Tests\Feature\Ops;

use App\Support\Ops\Backup\BackupPaths;
use App\Support\Ops\Backup\BackupVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 4B — backup commands.
 */
class BackupCommandsTest extends TestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupRoot = storage_path('framework/testing/backup-cmd-'.uniqid('', true));
        config([
            'backup.enabled' => true,
            'backup.root' => $this->backupRoot,
            'backup.database.driver' => 'fake',
            'backup.destination.driver' => 'local',
            'backup.retention.daily' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupRoot)) {
            File::deleteDirectory($this->backupRoot);
        }

        parent::tearDown();
    }

    public function test_backup_commands_are_registered(): void
    {
        Artisan::call('list', ['--raw' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('ops:backup-run', $output);
        $this->assertStringContainsString('ops:backup-check', $output);
        $this->assertStringContainsString('ops:backup-database', $output);
        $this->assertStringContainsString('ops:backup-media', $output);
        $this->assertStringContainsString('ops:backup-verify', $output);
        $this->assertStringContainsString('ops:backup-prune', $output);
    }

    public function test_backup_check_reports_readiness(): void
    {
        $this->artisan('ops:backup-check')
            ->assertSuccessful()
            ->expectsOutputToContain('status: ok');
    }

    public function test_backup_run_dry_run_writes_nothing(): void
    {
        $this->artisan('ops:backup-run --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run complete');

        $daily = app(BackupPaths::class)->tier('daily');
        $this->assertSame([], File::exists($daily) ? File::files($daily) : []);
    }

    public function test_backup_run_creates_verifiable_artifacts(): void
    {
        $this->artisan('ops:backup-run')->assertSuccessful();

        $daily = app(BackupPaths::class)->tier('daily');
        $db = collect(File::files($daily))->first(fn ($f) => str_ends_with($f->getFilename(), 'database.sql.gz'));
        $media = collect(File::files($daily))->first(fn ($f) => str_ends_with($f->getFilename(), 'media.tar.gz'));

        $this->assertNotNull($db);
        $this->assertNotNull($media);

        $verifier = app(BackupVerifier::class);
        $this->assertTrue($verifier->verifyFile($db->getPathname(), 'database')['ok']);
        $this->assertTrue($verifier->verifyFile($media->getPathname(), 'media')['ok']);

        $this->artisan('ops:backup-verify --latest')->assertSuccessful();
    }

    public function test_backup_database_dry_run_does_not_write_file(): void
    {
        $this->artisan('ops:backup-database --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run complete');

        $daily = app(BackupPaths::class)->tier('daily');
        $this->assertFalse(File::exists($daily) && count(File::files($daily)) > 0);
    }

    public function test_backup_prune_defaults_to_dry_run_without_deleting(): void
    {
        $paths = app(BackupPaths::class);
        $paths->ensureLayout();
        $daily = $paths->tier('daily');

        foreach (['2026-01-01_010101', '2026-01-02_010101', '2026-01-03_010101'] as $stamp) {
            File::put($daily.DIRECTORY_SEPARATOR.$stamp.'-database.sql.gz', 'x');
            touch($daily.DIRECTORY_SEPARATOR.$stamp.'-database.sql.gz', strtotime(str_replace('_', ' ', $stamp)) ?: time());
        }

        $before = count(File::files($daily));

        $this->artisan('ops:backup-prune')
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run (default)');

        $this->assertSame($before, count(File::files($daily)));
    }

    public function test_backup_check_json_output_has_no_secrets(): void
    {
        $this->artisan('ops:backup-check --json')
            ->assertSuccessful();

        $output = Artisan::output();
        $this->assertStringNotContainsStringIgnoringCase('password', $output);
        $this->assertStringNotContainsStringIgnoringCase('secret', $output);
    }
}
