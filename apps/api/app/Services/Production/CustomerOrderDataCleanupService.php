<?php

namespace App\Services\Production;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Production-safe pre-launch cleanup of customers + orders.
 * Preserves the complete catalog (products, variants, prices, stock, media).
 */
class CustomerOrderDataCleanupService
{
    private const CHUNK_SIZE = 500;

    public const FOCUS_EMAIL = 'sepprisegetsfashion@gmail.com';

    public const FOCUS_USER_ID = '019fd2d6-4915-71b9-9a33-ba619c2a3e04';

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $this->assertAllowedEnvironment();
        $this->assertCustomerIdentitySeparable();
        $this->assertCatalogExcludedFromManifest();

        $tableCounts = $this->countDeletionTables();
        $domainCounts = $this->groupDomainCounts($tableCounts);
        $customers = $this->listCustomersForReport();
        $orders = $this->listOrdersForReport();

        return [
            'environment' => (string) app()->environment(),
            'database_host' => (string) config('database.connections.'.config('database.default').'.host'),
            'database_name' => (string) config('database.connections.'.config('database.default').'.database'),
            'customer_identity_proven' => true,
            'catalog_excluded_proven' => true,
            'customer_users' => $this->countTable('users'),
            'admins' => $this->countTable('admins'),
            'customers' => $customers,
            'orders' => $orders,
            'domain_counts' => $domainCounts,
            'table_counts' => $tableCounts,
            'preserve_counts' => $this->countPreserveChecks(),
            'special_counts' => $this->countSpecialTargets(),
            'provenance' => $this->buildProvenanceReport(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $this->assertAllowedEnvironment();
        $this->assertCustomerIdentitySeparable();
        $this->assertCatalogExcludedFromManifest();

        $preserveBefore = $this->countPreserveChecks();
        $deletedTables = [];
        $specialDeleted = [];

        try {
            DB::transaction(function () use (&$deletedTables, &$specialDeleted): void {
                $specialDeleted = $this->deleteSpecialTargets();

                foreach (CustomerOrderDataCleanupManifest::DELETION_ORDER as $table) {
                    if (app()->environment('testing')
                        && config('testing.fail_customer_order_cleanup_after') === $table) {
                        throw new RuntimeException('Forced cleanup failure for rollback test.');
                    }

                    $deletedTables[$table] = $this->deleteTableChunked($table);
                }
            });
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Customer/order cleanup aborted and rolled back: '.$e->getMessage(),
                previous: $e,
            );
        }

        $preserveAfter = $this->countPreserveChecks();
        $catalogDrift = $this->detectCatalogDrift($preserveBefore, $preserveAfter);
        $postFailures = $this->assertPostCleanupZeros();

        return [
            'deleted_tables' => $deletedTables,
            'domain_deleted' => $this->groupDomainCounts($deletedTables),
            'preserve_counts_before' => $preserveBefore,
            'preserve_counts' => $preserveAfter,
            'catalog_drift' => $catalogDrift,
            'special_deleted' => $specialDeleted,
            'post_zero_ok' => $postFailures === [] && $catalogDrift === [],
            'post_zero_failures' => $postFailures,
        ];
    }

    public function assertAllowedEnvironment(): void
    {
        if (! app()->environment(['production', 'testing'])) {
            throw new RuntimeException(
                'Customer/order cleanup aborted: environment must be production (or testing for PHPUnit). Current: '
                .app()->environment(),
            );
        }
    }

    public function assertCustomerIdentitySeparable(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admins')) {
            throw new RuntimeException(
                'Cannot prove customer vs admin identity: required tables `users` and `admins` are missing.',
            );
        }

        $userTable = (new User)->getTable();
        $adminTable = (new Admin)->getTable();

        if ($userTable !== 'users' || $adminTable !== 'admins' || $userTable === $adminTable) {
            throw new RuntimeException(sprintf(
                'Cannot prove customer vs admin identity: User=`%s`, Admin=`%s`.',
                $userTable,
                $adminTable,
            ));
        }
    }

    public function assertCatalogExcludedFromManifest(): void
    {
        $overlap = array_values(array_intersect(
            CustomerOrderDataCleanupManifest::DELETION_ORDER,
            CustomerOrderDataCleanupManifest::FORBIDDEN_DELETE_TABLES,
        ));

        if ($overlap !== []) {
            throw new RuntimeException(
                'Customer/order cleanup aborted: catalog/foundation tables appear in deletion manifest: '
                .implode(', ', $overlap),
            );
        }
    }

    /**
     * Read-only provenance for pre-launch customers (especially Sepprise).
     * Does not guess — only reports what tables contain.
     *
     * @return list<array<string, mixed>>
     */
    public function buildProvenanceReport(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $users = DB::table('users')->orderBy('created_at')->get([
            'id',
            'email',
            'name',
            'created_at',
            'updated_at',
            'email_verified_at',
        ]);

        $reports = [];

        foreach ($users as $user) {
            $evidence = [];
            $conclusions = [];

            $evidence[] = [
                'source' => 'users',
                'detail' => sprintf(
                    'id=%s email=%s created_at=%s email_verified_at=%s',
                    $user->id,
                    $user->email,
                    $user->created_at ?? 'null',
                    $user->email_verified_at ?? 'null',
                ),
            ];

            $registrationSource = null;
            if (Schema::hasTable('customer_profiles')) {
                $profile = DB::table('customer_profiles')->where('user_id', $user->id)->first();
                if ($profile !== null) {
                    $registrationSource = $profile->registration_source ?? null;
                    $evidence[] = [
                        'source' => 'customer_profiles',
                        'detail' => sprintf(
                            'profile_id=%s registration_source=%s lifecycle=%s',
                            $profile->id ?? 'n/a',
                            $registrationSource ?? 'null',
                            $profile->lifecycle_status ?? 'null',
                        ),
                    ];
                } else {
                    $evidence[] = [
                        'source' => 'customer_profiles',
                        'detail' => 'no CRM profile row',
                    ];
                }
            }

            if (Schema::hasTable('customer_timeline_events') && Schema::hasTable('customer_profiles')) {
                $profileId = DB::table('customer_profiles')->where('user_id', $user->id)->value('id');
                if ($profileId) {
                    $timeline = DB::table('customer_timeline_events')
                        ->where('customer_profile_id', $profileId)
                        ->orderBy('occurred_at')
                        ->limit(10)
                        ->get(['event_type', 'title', 'occurred_at', 'metadata']);

                    foreach ($timeline as $event) {
                        $evidence[] = [
                            'source' => 'customer_timeline_events',
                            'detail' => sprintf(
                                '%s | %s | %s',
                                $event->event_type ?? '?',
                                $event->title ?? '',
                                $event->occurred_at ?? '',
                            ),
                        ];
                    }
                }
            }

            if (Schema::hasTable('activity_logs')) {
                $logs = DB::table('activity_logs')
                    ->where(function ($q) use ($user): void {
                        $q->where(function ($inner) use ($user): void {
                            $inner->where('actor_type', User::class)
                                ->where('actor_id', $user->id);
                        })->orWhere(function ($inner) use ($user): void {
                            $inner->where('subject_type', User::class)
                                ->where('subject_id', $user->id);
                        });
                    })
                    ->orderBy('created_at')
                    ->limit(20)
                    ->get(['event_type', 'action', 'actor_type', 'description', 'created_at']);

                if ($logs->isEmpty()) {
                    $evidence[] = [
                        'source' => 'activity_logs',
                        'detail' => 'no rows for this user as actor or subject',
                    ];
                } else {
                    foreach ($logs as $log) {
                        $evidence[] = [
                            'source' => 'activity_logs',
                            'detail' => sprintf(
                                '%s | %s | actor=%s | %s | %s',
                                $log->event_type ?? '?',
                                $log->action ?? '?',
                                $log->actor_type ?? '?',
                                $log->description ?? '',
                                $log->created_at ?? '',
                            ),
                        ];
                    }
                }
            } else {
                $evidence[] = [
                    'source' => 'activity_logs',
                    'detail' => 'table missing',
                ];
            }

            if (Schema::hasTable('personal_access_tokens')) {
                $tokenCount = (int) DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->count();
                $evidence[] = [
                    'source' => 'personal_access_tokens',
                    'detail' => 'customer tokens='.$tokenCount,
                ];
            }

            // Conclusions — only what evidence supports.
            if (is_string($registrationSource) && $registrationSource !== '') {
                $conclusions[] = 'customer_profiles.registration_source='.$registrationSource;
            }

            $hasAccountCreatedTimeline = collect($evidence)->contains(
                fn (array $row): bool => $row['source'] === 'customer_timeline_events'
                    && str_contains(strtolower($row['detail']), 'account'),
            );
            if ($hasAccountCreatedTimeline) {
                $conclusions[] = 'customer_timeline_events contains an account-created style event';
            }

            $hasRegistrationActivity = collect($evidence)->contains(
                fn (array $row): bool => $row['source'] === 'activity_logs'
                    && (
                        str_contains(strtolower($row['detail']), 'register')
                        || str_contains(strtolower($row['detail']), 'verified')
                        || str_contains(strtolower($row['detail']), 'email_verification')
                    ),
            );
            if ($hasRegistrationActivity) {
                $conclusions[] = 'activity_logs contain registration/verification-related events';
            }

            $source = is_string($registrationSource) ? strtolower($registrationSource) : '';
            $provenPath = 'cannot_be_proven';

            if (in_array($source, ['self_registration', 'checkout_registration'], true)) {
                $provenPath = 'customer_registration';
            } elseif ($source === 'admin_created') {
                $provenPath = 'admin_created';
            } elseif ($source === 'imported') {
                $provenPath = 'imported';
            } elseif ($hasRegistrationActivity) {
                $provenPath = 'activity_logs_indicate_registration_or_verification';
            }

            if ($provenPath === 'cannot_be_proven') {
                $conclusions[] = 'No conclusive registration/admin/import/seeder evidence in available tables';
            }

            $reports[] = [
                'user_id' => $user->id,
                'email' => $user->email,
                'focus_match' => strcasecmp((string) $user->email, self::FOCUS_EMAIL) === 0
                    || (string) $user->id === self::FOCUS_USER_ID,
                'proven_path' => $provenPath,
                'conclusions' => $conclusions,
                'evidence' => $evidence,
            ];
        }

        return $reports;
    }

    /**
     * @return list<array{id: string, email: string|null, name: string|null, created_at: string|null}>
     */
    private function listCustomersForReport(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->orderBy('created_at')
            ->get(['id', 'email', 'name', 'created_at'])
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'email' => $row->email !== null ? (string) $row->email : null,
                'name' => $row->name !== null ? (string) $row->name : null,
                'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, order_number: string|null, user_id: string|null, status: string|null, created_at: string|null}>
     */
    private function listOrdersForReport(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $columns = ['id', 'user_id', 'created_at'];
        if (Schema::hasColumn('orders', 'order_number')) {
            $columns[] = 'order_number';
        }
        if (Schema::hasColumn('orders', 'status')) {
            $columns[] = 'status';
        }

        return DB::table('orders')
            ->orderBy('created_at')
            ->get($columns)
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'order_number' => isset($row->order_number) ? (string) $row->order_number : null,
                'user_id' => $row->user_id !== null ? (string) $row->user_id : null,
                'status' => isset($row->status) ? (string) $row->status : null,
                'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function countDeletionTables(): array
    {
        $counts = [];

        foreach (CustomerOrderDataCleanupManifest::DELETION_ORDER as $table) {
            $counts[$table] = $this->countTable($table);
        }

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

        foreach (CustomerOrderDataCleanupManifest::DOMAIN_TABLES as $domain => $tables) {
            $sum = 0;
            foreach ($tables as $table) {
                $sum += (int) ($tableCounts[$table] ?? 0);
            }
            $domains[$domain] = $sum;
        }

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

        foreach (CustomerOrderDataCleanupManifest::PRESERVE_CHECKS as $label => $table) {
            $counts[$label] = $this->countTable($table);
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
        ];
    }

    /**
     * @return array<string, int>
     */
    private function deleteSpecialTargets(): array
    {
        return [
            'personal_access_tokens_customers' => $this->deleteCustomerPersonalAccessTokens(),
            'sessions_customers' => $this->deleteCustomerSessions(),
            'password_reset_tokens' => $this->deleteTableChunked('password_reset_tokens'),
        ];
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

    private function deleteTableChunked(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, 'id')) {
            return (int) DB::table($table)->delete();
        }

        $total = 0;

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
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, array{before: int, after: int}>
     */
    private function detectCatalogDrift(array $before, array $after): array
    {
        $catalogKeys = [
            'products',
            'product_variants',
            'variant_prices',
            'product_media',
            'product_images',
            'inventory',
            'variant_inventories',
            'china_commercial_stocks',
        ];

        $drift = [];
        foreach ($catalogKeys as $key) {
            $b = (int) ($before[$key] ?? 0);
            $a = (int) ($after[$key] ?? 0);
            if ($b !== $a) {
                $drift[$key] = ['before' => $b, 'after' => $a];
            }
        }

        return $drift;
    }

    /**
     * @return array<string, int>
     */
    private function assertPostCleanupZeros(): array
    {
        $requiredZero = [
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
