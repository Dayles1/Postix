<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the person last wrote to the listener's account in private: the
 * nudge counts the penalties nobody answered since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->timestamp('last_private_message_at')->nullable()->after('dm_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn('last_private_message_at');
        });
    }
};
