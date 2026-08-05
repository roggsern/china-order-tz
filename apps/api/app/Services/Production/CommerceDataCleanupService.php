<?php

namespace App\Services\Production;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Production-safe cleanup of test/demo commerce data.
 *
 * Never touches admins, taxonomy, stores, CMS, settings, or notification templates.
 */
class CommerceDataCleanupService
{
    private const CHUNK_SIZE = 500;

    /**
     * @return array{
     *     environment: string,
     *     database_host: string,
     *     database_name: string,
     *     customer_identity_proven: bool,
     *     customer_users: int,
     *     admins: int,
     *     domain_counts: array<string, int>,
     *     table_counts: array<string, int>,
     *     preserve_counts: array<string, int>,
     *     media: array{rows: int, files_expected: int, paths: list<string>},
     *     special_counts: array<string, int>
     * }
     */
    public function preview(): array
    {
        $this->assertCustomerIdentitySeparable();

        $tableCounts = $this->countDeletionTables();
        $domainCounts = $this->groupDomainCounts($tableCounts);
        $media = $this->collectRemovableMedia(dryRun: true);

        return [
            'environment' => (string) app()->environment(),
            'database_host' => (string) config('database.connections.'.config('database.default').'.host'),
            'database_name' => (string) config('database.connections.'.config('database.default').'.database'),
            'customer_identity_proven' => true,
            'customer_users' => $this->countTable('users'),
            'admins' => $this->countTable('admins'),
            'domain_counts' => $domainCounts,
            'table_counts' => $tableCounts,
            'preserve_counts' => $this->countPreserveChecks(),
            'media' => [
                'rows' => $media['rows'],
                'files_expected' => count($media['paths']),
                'paths' => $media['paths'],
            ],
            'special_counts' => $this->countSpecialTargets(),
        ];
    }

    /**
     * @return array{
     *     deleted_tables: array<string, int>,
     *     domain_deleted: array<string, int>,
     *     preserve_counts: array<string, int>,
     *     media: array{deleted_files: int, missing_files: int, skipped_unsafe: int},
     *     special_deleted: array<string, int>,
     *     post_zero_ok: bool,
     *     post_zero_failures: array<string, int>
     * }
     */
    public function execute(): array
    {
        $this->assertCustomerIdentitySeparable();

        $mediaPlan = $this->collectRemovableMedia(dryRun: false);
        $deletedTables = [];
        $specialDeleted = [];

        try {
            DB::transaction(function () use (&$deletedTables, &$specialDeleted): void {
                $specialDeleted = $this->deleteSpecialTargets();

                foreach (CommerceDataCleanupManifest::DELETION_ORDER as $table) {
                    // Testing hook: force mid-transaction failure to prove rollback.
                    if (app()->environment('testing')
                        && config('testing.fail_commerce_cleanup_after') === $table) {
                        throw new RuntimeException('Forced cleanup failure for rollback test.');
                    }

                    $deletedTables[$table] = $this->deleteTableChunked($table);
                }

                $deletedTables['attribute_dependencies'] = $this->deleteProductScopedAttributeDependencies();
            });
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Commerce cleanup aborted and rolled back: '.$e->getMessage(),
                previous: $e,
            );
        }

        $mediaResult = $this->deleteCollectedMediaFiles($mediaPlan['paths']);
        $postFailures = $this->assertPostCleanupZeros();

        return [
            'deleted_tables' => $deletedTables,
            'domain_deleted' => $this->groupDomainCounts($deletedTables),
            'preserve_counts' => $this->countPreserveChecks(),
            'media' => $mediaResult,
            'special_deleted' => $specialDeleted,
            'post_zero_ok' => $postFailures === [],
            'post_zero_failures' => $postFailures,
        ];
    }

    /**
     * Prove customers live in `users` and admins in `admins` (separate models/tables).
     */
    public function assertCustomerIdentitySeparable(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admins')) {
            throw new RuntimeException(
                'Cannot prove customer vs admin identity: required tables `users` and `admins` are missing.',
            );
        }

        $userTable = (new User)->getTable();
        $adminTable = (new Admin)->getTable();

        if ($userTable !== 'users' || $adminTable !== 'admins') {
            throw new RuntimeException(sprintf(
                'Cannot prove customer vs admin identity: User uses `%s`, Admin uses `%s` (expected users/admins).',
                $userTable,
                $adminTable,
            ));
        }

        if ($userTable === $adminTable) {
            throw new RuntimeException('Cannot prove customer vs admin identity: models share one table.');
        }

        if (! is_a(User::class, \Illuminate\Contracts\Auth\Authenticatable::class, true)) {
            throw new RuntimeException('User model is not authenticatable.');
        }

        if (! is_a(Admin::class, \Illuminate\Contracts\Auth\Authenticatable::class, true)) {
            throw new RuntimeException('Admin model is not authenticatable.');
        }
    }

    /**
     * @return array<string, int>
     */
    private function countDeletionTables(): array
    {
        $counts = [];

        foreach (CommerceDataCleanupManifest::DELETION_ORDER as $table) {
            $counts[$table] = $this->countTable($table);
        }

        $counts['attribute_dependencies'] = $this->countProductScopedAttributeDependencies();

        foreach ($this->countSpecialTargets() as $key => $count) {
            $counts[$key] = $count;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $tableCounts
     * @return array<string, int>
     */
    private function groupDomainCounts(array $tableCounts): array
    {
        $domains = [];

        foreach (CommerceDataCleanupManifest::DOMAIN_TABLES as $domain => $tables) {
            $sum = 0;
            foreach ($tables as $table) {
                $sum += (int) ($tableCounts[$table] ?? 0);
            }
            $domains[$domain] = $sum;
        }

        $domains['attribute_dependencies_product_scoped'] = (int) ($tableCounts['attribute_dependencies'] ?? 0);
        $domains['customer_tokens'] = (int) ($tableCounts['personal_access_tokens_customers'] ?? 0);
        $domains['customer_sessions'] = (int) ($tableCounts['sessions_customers'] ?? 0);
        $domains['password_reset_tokens'] = (int) ($tableCounts['password_reset_tokens'] ?? 0);

        return $domains;
    }

    /**
     * @return array<string, int>
     */
    private function countPreserveChecks(): array
    {
        $counts = [];

        foreach (CommerceDataCleanupManifest::PRESERVE_CHECKS as $label => $table) {
            $counts[$label] = $this->countTable(is_array($table) ? $table[0] : $table);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function countSpecialTargets(): array
    {
        return [
            'personal_access_tokens_customers' => $this->countCustomerPersonalAccessTokens(),
            'sessions_customers' => $this->countCustomerSessions(),
            'password_reset_tokens' => $this->countTable('password_reset_tokens'),
            'attribute_dependencies' => $this->countProductScopedAttributeDependencies(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function deleteSpecialTargets(): array
    {
        $deleted = [
            'personal_access_tokens_customers' => $this->deleteCustomerPersonalAccessTokens(),
            'sessions_customers' => $this->deleteCustomerSessions(),
            'password_reset_tokens' => $this->deleteTableChunked('password_reset_tokens'),
        ];

        return $deleted;
    }

    private function countCustomerPersonalAccessTokens(): int
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return 0;
        }

        return (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->count();
    }

    private function deleteCustomerPersonalAccessTokens(): int
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return 0;
        }

        return (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->delete();
    }

    private function countCustomerSessions(): int
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'user_id')) {
            return 0;
        }

        return (int) DB::table('sessions')->whereNotNull('user_id')->count();
    }

    private function deleteCustomerSessions(): int
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'user_id')) {
            return 0;
        }

        return (int) DB::table('sessions')->whereNotNull('user_id')->delete();
    }

    private function countProductScopedAttributeDependencies(): int
    {
        if (! Schema::hasTable('attribute_dependencies') || ! Schema::hasColumn('attribute_dependencies', 'product_id')) {
            return 0;
        }

        return (int) DB::table('attribute_dependencies')->whereNotNull('product_id')->count();
    }

    private function deleteProductScopedAttributeDependencies(): int
    {
        if (! Schema::hasTable('attribute_dependencies') || ! Schema::hasColumn('attribute_dependencies', 'product_id')) {
            return 0;
        }

        return (int) DB::table('attribute_dependencies')->whereNotNull('product_id')->delete();
    }

    /**
     * @return array{rows: int, paths: list<string>}
     */
    private function collectRemovableMedia(bool $dryRun): array
    {
        $paths = [];
        $rows = 0;

        if (Schema::hasTable('product_media')) {
            $query = DB::table('product_media')->select(['url', 'thumbnail_url']);
            foreach ($query->cursor() as $row) {
                $rows++;
                foreach ([$row->url ?? null, $row->thumbnail_url ?? null] as $url) {
                    $path = $this->resolvePublicUrlToPath(is_string($url) ? $url : null);
                    if ($path !== null && $this->isSafeProductOwnedPath($path)) {
                        $paths[] = $path;
                    }
                }
            }
        }

        if (Schema::hasTable('product_images')) {
            $query = DB::table('product_images')->select(['path']);
            foreach ($query->cursor() as $row) {
                $rows++;
                $path = is_string($row->path ?? null) ? ltrim($row->path, '/') : null;
                if ($path !== null && $this->isSafeProductOwnedPath($path)) {
                    $paths[] = $path;
                }
            }
        }

        if (Schema::hasTable('china_order_attachments')) {
            $query = DB::table('china_order_attachments')->select(['path']);
            foreach ($query->cursor() as $row) {
                $rows++;
                $path = is_string($row->path ?? null) ? ltrim($row->path, '/') : null;
                if ($path !== null && $this->isSafeAttachmentPath($path)) {
                    $paths[] = $path;
                }
            }
        }

        $paths = array_values(array_unique($paths));

        // Dry-run path list can be large; keep full list for execute, cap display elsewhere.
        unset($dryRun);

        return ['rows' => $rows, 'paths' => $paths];
    }

    private function resolvePublicUrlToPath(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $publicBase = rtrim(Storage::disk('public')->url(''), '/');
        if (Str::startsWith($url, $publicBase.'/')) {
            return ltrim(Str::after($url, $publicBase.'/'), '/');
        }

        if (Str::startsWith($url, '/storage/')) {
            return ltrim(Str::after($url, '/storage/'), '/');
        }

        if (Str::startsWith($url, 'storage/')) {
            return ltrim(Str::after($url, 'storage/'), '/');
        }

        // Already a relative disk path (e.g. products/uuid.jpg)
        if (! Str::contains($url, '://') && ! Str::startsWith($url, '/')) {
            return ltrim($url, '/');
        }

        return null;
    }

    private function isSafeProductOwnedPath(string $path): bool
    {
        if ($path === '' || Str::contains($path, '..') || Str::startsWith($path, '/')) {
            return false;
        }

        return Str::startsWith($path, 'products/')
            || Str::startsWith($path, 'demo-products/');
    }

    private function isSafeAttachmentPath(string $path): bool
    {
        if ($path === '' || Str::contains($path, '..') || Str::startsWith($path, '/')) {
            return false;
        }

        // Only delete china-order attachment paths we recognize; never CMS/store branding.
        return Str::startsWith($path, 'china-orders/')
            || Str::startsWith($path, 'china_orders/')
            || Str::startsWith($path, 'china-order-attachments/')
            || Str::startsWith($path, 'products/');
    }

    /**
     * @param  list<string>  $paths
     * @return array{deleted_files: int, missing_files: int, skipped_unsafe: int}
     */
    private function deleteCollectedMediaFiles(array $paths): array
    {
        $deleted = 0;
        $missing = 0;
        $skipped = 0;
        $disk = Storage::disk('public');

        foreach ($paths as $path) {
            if (! $this->isSafeProductOwnedPath($path) && ! $this->isSafeAttachmentPath($path)) {
                $skipped++;

                continue;
            }

            if (! $disk->exists($path)) {
                $missing++;

                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        return [
            'deleted_files' => $deleted,
            'missing_files' => $missing,
            'skipped_unsafe' => $skipped,
        ];
    }

    private function deleteTableChunked(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        // Prefer hard delete of all rows (including soft-deleted) via query builder.
        if (! Schema::hasColumn($table, 'id')) {
            return (int) DB::table($table)->delete();
        }

        $total = 0;

        // UUID or int PKs: delete in batches by fetching ids.
        do {
            $ids = DB::table($table)->orderBy('id')->limit(self::CHUNK_SIZE)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $total += (int) DB::table($table)->whereIn('id', $ids->all())->delete();
        } while ($ids->count() === self::CHUNK_SIZE);

        return $total;
    }

    private function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    /**
     * @return array<string, int>
     */
    private function assertPostCleanupZeros(): array
    {
        $requiredZero = [
            'products',
            'product_variants',
            'users',
            'carts',
            'wishlists',
            'orders',
            'order_items',
            'payments',
            'payment_transactions',
            'refunds',
            'fulfillments',
            'shipments',
            'reviews',
            'support_tickets',
            'notifications',
            'storefront_events',
        ];

        $failures = [];
        foreach ($requiredZero as $table) {
            $count = $this->countTable($table);
            if ($count > 0) {
                $failures[$table] = $count;
            }
        }

        return $failures;
    }
}
