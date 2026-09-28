<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the sessions page shows about an account.
 *
 * The profile is written by the CLI commands (ta / tc / tp /
 * telegram:account-check), the only processes allowed to open a
 * MadelineProto session. The web side just reads it back.
 *
 * session_path becomes nullable because telegram:logout has always
 * cleared it - on the old NOT NULL column that update failed, and a
 * logged-out account kept pointing at a deleted session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->string('session_path')->nullable()->change();

            $table->unsignedBigInteger('telegram_user_id')
                ->nullable()
                ->after('phone');

            $table->string('first_name')->nullable()->after('telegram_user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('username')->nullable()->after('last_name');

            $table->string('password_hint')->nullable()->after('status');
            $table->text('last_error')->nullable()->after('password_hint');
            $table->timestamp('last_checked_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_user_id',
                'first_name',
                'last_name',
                'username',
                'password_hint',
                'last_error',
                'last_checked_at',
            ]);
        });
    }
};
