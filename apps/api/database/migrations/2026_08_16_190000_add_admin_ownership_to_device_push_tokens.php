<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wave admin-mobile — allow DevicePushToken ownership by Admin XOR User.
 * Existing customer rows remain valid (user_id set, admin_id null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_push_tokens')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->upgradeSqlite();

            return;
        }

        Schema::table('device_push_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('device_push_tokens', 'admin_id')) {
                $table->foreignUuid('admin_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('admins')
                    ->cascadeOnDelete();
                $table->index(['admin_id', 'is_active'], 'device_push_tokens_admin_active_index');
            }
        });

        Schema::table('device_push_tokens', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE device_push_tokens MODIFY user_id CHAR(36) NULL');
        }

        Schema::table('device_push_tokens', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        $this->ensureMysqlOwnerXorConstraint();
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_push_tokens')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->downgradeSqlite();

            return;
        }

        $this->dropMysqlOwnerXorConstraint();

        if (Schema::hasColumn('device_push_tokens', 'admin_id')) {
            DB::table('device_push_tokens')->whereNotNull('admin_id')->delete();

            Schema::table('device_push_tokens', function (Blueprint $table): void {
                $table->dropForeign(['admin_id']);
            });

            Schema::table('device_push_tokens', function (Blueprint $table): void {
                $table->dropIndex('device_push_tokens_admin_active_index');
                $table->dropColumn('admin_id');
            });
        }

        Schema::table('device_push_tokens', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE device_push_tokens MODIFY user_id CHAR(36) NOT NULL');
        }

        Schema::table('device_push_tokens', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    private function upgradeSqlite(): void
    {
        if (Schema::hasColumn('device_push_tokens', 'admin_id')) {
            return;
        }

        // SQLite keeps index names DB-wide after rename — drop before recreate.
        Schema::table('device_push_tokens', function (Blueprint $table): void {
            $table->dropUnique('device_push_tokens_push_token_unique');
            $table->dropUnique('device_push_tokens_installation_id_unique');
            $table->dropIndex('device_push_tokens_user_active_index');
            $table->dropIndex(['last_seen_at']);
        });

        Schema::rename('device_push_tokens', 'device_push_tokens_old');

        Schema::create('device_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->cascadeOnDelete();
            $table->string('push_token', 512);
            $table->string('provider', 32);
            $table->string('platform', 16);
            $table->uuid('installation_id');
            $table->string('app_version', 64)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('push_token', 'device_push_tokens_push_token_unique');
            $table->unique('installation_id', 'device_push_tokens_installation_id_unique');
            $table->index(['user_id', 'is_active'], 'device_push_tokens_user_active_index');
            $table->index(['admin_id', 'is_active'], 'device_push_tokens_admin_active_index');
            $table->index('last_seen_at');
        });

        // SQLite CHECK for XOR ownership (MySQL uses named CONSTRAINT).
        DB::statement(
            'CREATE TRIGGER device_push_tokens_owner_xor_insert
             BEFORE INSERT ON device_push_tokens
             FOR EACH ROW
             WHEN NOT (
                (NEW.user_id IS NOT NULL AND NEW.admin_id IS NULL)
                OR (NEW.user_id IS NULL AND NEW.admin_id IS NOT NULL)
             )
             BEGIN
               SELECT RAISE(ABORT, \'device_push_tokens owner XOR violated\');
             END'
        );
        DB::statement(
            'CREATE TRIGGER device_push_tokens_owner_xor_update
             BEFORE UPDATE ON device_push_tokens
             FOR EACH ROW
             WHEN NOT (
                (NEW.user_id IS NOT NULL AND NEW.admin_id IS NULL)
                OR (NEW.user_id IS NULL AND NEW.admin_id IS NOT NULL)
             )
             BEGIN
               SELECT RAISE(ABORT, \'device_push_tokens owner XOR violated\');
             END'
        );

        DB::statement(
            'INSERT INTO device_push_tokens (
                id, user_id, admin_id, push_token, provider, platform, installation_id,
                app_version, device_name, is_active, last_seen_at, revoked_at, created_at, updated_at
             )
             SELECT
                id, user_id, NULL, push_token, provider, platform, installation_id,
                app_version, device_name, is_active, last_seen_at, revoked_at, created_at, updated_at
             FROM device_push_tokens_old'
        );

        Schema::drop('device_push_tokens_old');
    }

    private function downgradeSqlite(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS device_push_tokens_owner_xor_insert');
        DB::statement('DROP TRIGGER IF EXISTS device_push_tokens_owner_xor_update');

        DB::table('device_push_tokens')->whereNotNull('admin_id')->delete();

        Schema::rename('device_push_tokens', 'device_push_tokens_new');

        Schema::create('device_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('push_token', 512);
            $table->string('provider', 32);
            $table->string('platform', 16);
            $table->uuid('installation_id');
            $table->string('app_version', 64)->nullable();
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('push_token', 'device_push_tokens_push_token_unique');
            $table->unique('installation_id', 'device_push_tokens_installation_id_unique');
            $table->index(['user_id', 'is_active'], 'device_push_tokens_user_active_index');
            $table->index('last_seen_at');
        });

        DB::statement(
            'INSERT INTO device_push_tokens (
                id, user_id, push_token, provider, platform, installation_id,
                app_version, device_name, is_active, last_seen_at, revoked_at, created_at, updated_at
             )
             SELECT
                id, user_id, push_token, provider, platform, installation_id,
                app_version, device_name, is_active, last_seen_at, revoked_at, created_at, updated_at
             FROM device_push_tokens_new
             WHERE user_id IS NOT NULL'
        );

        Schema::drop('device_push_tokens_new');
    }

    private function ensureMysqlOwnerXorConstraint(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropMysqlOwnerXorConstraint();

        DB::statement(
            'ALTER TABLE device_push_tokens
             ADD CONSTRAINT device_push_tokens_owner_xor
             CHECK (
                (user_id IS NOT NULL AND admin_id IS NULL)
                OR (user_id IS NULL AND admin_id IS NOT NULL)
             )'
        );
    }

    private function dropMysqlOwnerXorConstraint(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE device_push_tokens DROP CHECK device_push_tokens_owner_xor');
        } catch (\Throwable) {
            // Constraint may not exist yet.
        }
    }
};
