<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('moderation_note')->nullable()->after('status');
            $table->foreignUuid('moderated_by_admin_id')->nullable()->after('moderation_note')
                ->constrained('admins')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by_admin_id');
            $table->dropColumn(['moderation_note', 'moderated_at']);
        });
    }
};
