<?php

namespace Tests\Unit\Production;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * RC1-G4C.3 / G4C.5 — static production Compose + deploy script checks (no Docker runtime).
 */
class ProductionComposeDefinitionTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    private static function prodComposeRaw(): string
    {
        $path = self::repoRoot().'/docker-compose.prod.yml';

        return (string) file_get_contents($path);
    }

    private static function deployScript(): string
    {
        return (string) file_get_contents(self::repoRoot().'/scripts/deploy-api-compose.sh');
    }

    private static function entrypointProd(): string
    {
        return (string) file_get_contents(self::repoRoot().'/docker/php/entrypoint.prod.sh');
    }

    /**
     * @return array<string, mixed>
     */
    private static function prodCompose(): array
    {
        return Yaml::parse(self::prodComposeRaw());
    }

    public function test_queue_and_scheduler_clear_workers_profile(): void
    {
        $compose = self::prodCompose();
        $services = $compose['services'] ?? [];

        foreach (['queue', 'scheduler'] as $name) {
            $this->assertArrayHasKey($name, $services, "Missing production service: {$name}");
            $this->assertArrayHasKey('profiles', $services[$name]);
            $this->assertSame([], $services[$name]['profiles'], "{$name} must start by default in production.");
        }
    }

    public function test_queue_and_scheduler_skip_migrations(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertSame(2, substr_count($raw, 'SKIP_MIGRATIONS: "true"'));
    }

    public function test_entrypoint_runs_migrations_only_when_not_skipped(): void
    {
        $entrypoint = self::entrypointProd();

        $this->assertStringContainsString('SKIP_MIGRATIONS', $entrypoint);
        $this->assertStringContainsString('php artisan migrate --force --no-interaction', $entrypoint);
    }

    public function test_production_compose_does_not_reference_development_bind_mounts(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertStringNotContainsString('./apps/api', $raw);
        $this->assertStringNotContainsString('./apps/web', $raw);
    }

    public function test_api_queue_scheduler_and_nginx_override_volumes_for_runtime_storage(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertStringContainsString('api_storage:/var/www/html/storage', $raw);
        $this->assertStringContainsString('app_backups:/var/backups/china-order-tz', $raw);
        $this->assertStringContainsString('api_storage:/var/www/html/storage:ro', $raw);
        $this->assertStringContainsString('volumes: !override', $raw);
    }

    public function test_web_production_service_clears_development_mounts_and_env(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertStringContainsString("volumes: !override []\n", $raw);
        $this->assertStringContainsString("environment: !override\n", $raw);
        $this->assertStringContainsString('NODE_ENV: production', $raw);
    }

    public function test_nginx_uses_production_image(): void
    {
        $compose = self::prodCompose();

        $this->assertSame(
            'docker/nginx/Dockerfile.prod',
            $compose['services']['nginx']['build']['dockerfile'] ?? null
        );
    }

    public function test_deploy_script_runs_preflight_before_compose_up(): void
    {
        $script = self::deployScript();

        $preflightPos = strpos($script, 'production_preflight_static');
        $upPos = strpos($script, 'up -d --build');

        $this->assertNotFalse($preflightPos);
        $this->assertNotFalse($upPos);
        $this->assertLessThan($upPos, $preflightPos, 'Static preflight must run before compose up.');
    }

    public function test_deploy_script_fails_on_production_env_check(): void
    {
        $script = self::deployScript();

        $this->assertStringContainsString('ops:production-env-check', $script);
        $this->assertDoesNotMatchRegularExpression(
            '/ops:production-env-check\s*\|\|\s*true/',
            $script,
            'Production env validation must not be silenced with || true.'
        );
    }

    public function test_deploy_script_requires_nmb_validation(): void
    {
        $script = self::deployScript();

        $this->assertStringContainsString('nmb:validate-config', $script);
        $this->assertDoesNotMatchRegularExpression(
            '/nmb:validate-config\s*\|\|\s*true/',
            $script,
            'NMB validation must not be silenced with || true.'
        );
    }

    public function test_deploy_script_waits_for_scheduler_health(): void
    {
        $script = self::deployScript();

        $this->assertStringContainsString('wait_for_scheduler_health', $script);
        $this->assertStringContainsString('exec -T scheduler php artisan ops:health --json', $script);
    }

    public function test_deploy_script_supports_optional_pre_deploy_backup(): void
    {
        $script = self::deployScript();

        $this->assertStringContainsString('PRE_DEPLOY_BACKUP', $script);
        $this->assertStringContainsString('ops:backup-run', $script);
        $this->assertStringContainsString('--entrypoint php', $script);
    }

    public function test_static_preflight_script_exists(): void
    {
        $path = self::repoRoot().'/scripts/validate-production-deploy.sh';

        $this->assertFileExists($path);
        $this->assertStringContainsString('production_preflight_static', (string) file_get_contents($path));
    }

    #[DataProvider('requiredProductionServicesProvider')]
    public function test_prod_compose_declares_required_service(string $service): void
    {
        $compose = self::prodCompose();

        $this->assertArrayHasKey($service, $compose['services'] ?? [], "Missing {$service} in docker-compose.prod.yml");
    }

    /**
     * @return list<array{0: string}>
     */
    public static function requiredProductionServicesProvider(): array
    {
        return [
            ['mysql'],
            ['api'],
            ['nginx'],
            ['web'],
            ['queue'],
            ['scheduler'],
        ];
    }
}
