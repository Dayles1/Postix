<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What goes into a person's private chat, one switch each, under
 * dm_enabled (OperationUser::MESSAGE_SWITCHES): the penalties, their
 * comment, our greeting before them, the nudge and the auto replies.
 * Everything stays on, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->boolean('penalties_enabled')->default(true)->after('dm_enabled');
            $table->boolean('penalty_comments_enabled')->default(true)->after('penalties_enabled');
            $table->boolean('greet_first_enabled')->default(true)->after('penalty_comments_enabled');
            $table->boolean('nudge_enabled')->default(true)->after('greet_first_enabled');
            $table->boolean('auto_replies_enabled')->default(true)->after('nudge_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn([
                'penalties_enabled',
                'penalty_comments_enabled',
                'greet_first_enabled',
                'nudge_enabled',
                'auto_replies_enabled',
            ]);
        });
    }
};
