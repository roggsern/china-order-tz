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
        $override = getenv('MONOREPO_ROOT');
        if (is_string($override) && $override !== '' && is_file($override.'/docker-compose.prod.yml')) {
            return rtrim($override, '/\\');
        }

        $candidates = [
            dirname(__DIR__, 5),
            dirname(base_path(), 2),
        ];

        foreach ($candidates as $dir) {
            if (is_file($dir.'/docker-compose.prod.yml')) {
                return $dir;
            }
        }

        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            if (is_file($dir.'/docker-compose.prod.yml')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        self::fail('Monorepo root not found (expected docker-compose.prod.yml). Run this test from a full repository checkout.');
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
        return Yaml::parse(self::prodComposeRaw(), Yaml::PARSE_CUSTOM_TAGS);
    }

    public function test_queue_and_scheduler_clear_workers_profile(): void
    {
        $compose = self::prodCompose();
        $services = $compose['services'] ?? [];

        foreach (['queue', 'scheduler'] as $name) {
            $this->assertArrayHasKey($name, $services, "Missing production service: {$name}");
            $this->assertArrayHasKey('profiles', $services[$name]);

            $profiles = $services[$name]['profiles'];
            $isCleared = $profiles === [] || ($profiles instanceof \Symfony\Component\Yaml\Tag\TaggedValue && $profiles->getTag() === 'reset' && $profiles->getValue() === []);

            $this->assertTrue($isCleared, "{$name} must start by default in production.");
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

        $this->assertMatchesRegularExpression('/volumes: !override \[\]\r?\n/', $raw);
        $this->assertMatchesRegularExpression('/environment: !override\r?\n/', $raw);
        $this->assertStringContainsString('NODE_ENV: production', $raw);
    }

    private static function nodeDockerfile(): string
    {
        return (string) file_get_contents(self::repoRoot().'/docker/node/Dockerfile');
    }

    public function test_node_dockerfile_declares_public_url_build_args_before_build(): void
    {
        $dockerfile = self::nodeDockerfile();
        $buildPos = strpos($dockerfile, 'RUN npm run build');

        $this->assertNotFalse($buildPos);
        $this->assertStringContainsString('ARG NEXT_PUBLIC_APP_URL', $dockerfile);
        $this->assertStringContainsString('ARG NEXT_PUBLIC_API_URL', $dockerfile);
        $this->assertStringContainsString('ENV NEXT_PUBLIC_APP_URL=${NEXT_PUBLIC_APP_URL}', $dockerfile);
        $this->assertStringContainsString('ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}', $dockerfile);
        $this->assertLessThan($buildPos, strpos($dockerfile, 'ARG NEXT_PUBLIC_APP_URL'));
        $this->assertLessThan($buildPos, strpos($dockerfile, 'ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}'));
    }

    public function test_prod_compose_web_passes_public_url_build_args(): void
    {
        $compose = self::prodCompose();
        $args = $compose['services']['web']['build']['args'] ?? [];

        $this->assertSame('${NEXT_PUBLIC_APP_URL}', $args['NEXT_PUBLIC_APP_URL'] ?? null);
        $this->assertSame('${NEXT_PUBLIC_API_URL}', $args['NEXT_PUBLIC_API_URL'] ?? null);
    }

    public function test_prod_compose_web_runtime_keeps_internal_api_url(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertStringContainsString('API_INTERNAL_URL: ${API_INTERNAL_URL:-http://nginx}', $raw);
    }

    public function test_prod_compose_web_build_args_source_production_env_without_localhost_defaults(): void
    {
        $raw = self::prodComposeRaw();

        $this->assertStringContainsString('NEXT_PUBLIC_APP_URL: ${NEXT_PUBLIC_APP_URL}', $raw);
        $this->assertStringContainsString('NEXT_PUBLIC_API_URL: ${NEXT_PUBLIC_API_URL}', $raw);
        $this->assertStringNotContainsString('NEXT_PUBLIC_APP_URL: http://localhost', $raw);
        $this->assertStringNotContainsString('NEXT_PUBLIC_API_URL: http://localhost', $raw);
    }

    public function test_production_env_template_declares_public_urls_for_web_build(): void
    {
        $template = (string) file_get_contents(self::repoRoot().'/.env.production.example');

        $this->assertStringContainsString('NEXT_PUBLIC_APP_URL=https://www.chinaordertz.com', $template);
        $this->assertStringContainsString('NEXT_PUBLIC_API_URL=https://api.chinaordertz.com', $template);
    }

    public function test_production_env_template_declares_mysql_credentials_as_source_of_truth(): void
    {
        $template = (string) file_get_contents(self::repoRoot().'/.env.production.example');

        foreach ([
            'MYSQL_ROOT_PASSWORD=',
            'MYSQL_DATABASE=china_order_tz',
            'MYSQL_USER=china_order',
            'MYSQL_PASSWORD=',
            'MYSQL_* is the source of truth',
            'DB_* must mirror MYSQL_*',
        ] as $needle) {
            $this->assertStringContainsString($needle, $template);
        }
    }

    public function test_api_production_env_template_points_to_root_template(): void
    {
        $template = (string) file_get_contents(self::repoRoot().'/apps/api/.env.production.example');

        $this->assertStringContainsString('DEPRECATED', $template);
        $this->assertStringContainsString('.env.production.example', $template);
    }

    public function test_deploy_script_loads_dotenv_before_mysql_shell_usage(): void
    {
        $script = self::deployScript();

        $loadPos = strpos($script, 'production_load_dotenv');
        $mysqlPingPos = strpos($script, 'mysqladmin ping');

        $this->assertNotFalse($loadPos);
        $this->assertNotFalse($mysqlPingPos);
        $this->assertLessThan($mysqlPingPos, $loadPos, 'production_load_dotenv must run before mysqladmin ping usage.');
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

    public function test_php_image_ships_upload_limits_ini(): void
    {
        $iniPath = self::repoRoot().'/docker/php/uploads.prod.ini';
        $dockerfilePath = self::repoRoot().'/docker/php/Dockerfile';
        $nginxConf = self::repoRoot().'/docker/nginx/default.conf';

        $this->assertFileExists($iniPath);
        $ini = (string) file_get_contents($iniPath);
        $this->assertStringContainsString('upload_max_filesize = 10M', $ini);
        $this->assertStringContainsString('post_max_size = 12M', $ini);
        $this->assertStringContainsString('memory_limit = 256M', $ini);

        $dockerfile = (string) file_get_contents($dockerfilePath);
        $this->assertStringContainsString('docker/php/uploads.prod.ini', $dockerfile);
        $this->assertStringContainsString('zz-uploads.ini', $dockerfile);

        $this->assertStringContainsString('client_max_body_size 20M', (string) file_get_contents($nginxConf));
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
