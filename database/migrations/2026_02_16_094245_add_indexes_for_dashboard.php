<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->index(['message_group_id', 'sent_at'], 'idx_tm_group_sent_at');
            $table->index('sent_at', 'idx_tm_sent_at');
            $table->index(['status', 'peer'], 'idx_tm_status_peer');
        });

        Schema::table('message_groups', function (Blueprint $table) {
            $table->index('user_phone_id', 'idx_mg_user_phone');
        });

        Schema::table('user_phones', function (Blueprint $table) {
            $table->index(['user_id', 'is_active'], 'idx_up_user_active');
        });

        try {
            DB::statement("ALTER TABLE telegram_messages ADD FULLTEXT idx_tm_message_text (message_text)");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropIndex('idx_tm_group_sent_at');
            $table->dropIndex('idx_tm_sent_at');
            $table->dropIndex('idx_tm_status_peer');
            // drop fulltext - use try/catch
            try {
                DB::statement("ALTER TABLE telegram_messages DROP INDEX idx_tm_message_text");
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('message_groups', function (Blueprint $table) {
            $table->dropIndex('idx_mg_user_phone');
        });

        Schema::table('user_phones', function (Blueprint $table) {
            $table->dropIndex('idx_up_user_active');
        });
    }
};
