<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            /*
             * Operators are created automatically by the message parser, so
             * every existing row must keep working exactly as before: active,
             * and eligible for a direct message once someone fills in the
             * Telegram username or id on the operators page.
             */
            $table->boolean('is_active')
                ->default(true)
                ->after('telegram_id');

            $table->boolean('dm_enabled')
                ->default(true)
                ->after('is_active');

            $table->timestamp('dm_last_sent_at')
                ->nullable()
                ->after('dm_enabled');

            /*
             * Why the last direct message could not be delivered (unknown
             * username, privacy settings, peer never seen by the account).
             * Shown on the operators page so the reason is visible without
             * digging through the listener log.
             */
            $table->text('dm_last_error')
                ->nullable()
                ->after('dm_last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'dm_enabled',
                'dm_last_sent_at',
                'dm_last_error',
            ]);
        });
    }
};
