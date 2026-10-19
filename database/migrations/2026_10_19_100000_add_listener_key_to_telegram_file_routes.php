<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account a kind of file goes out from: a listener key, "_dc" for
 * Driver Check, nothing to follow the general owner of the files
 * (Listeners -> Who does what).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_file_routes', function (Blueprint $table): void {
            $table->string('listener_key', 32)->nullable()->after('target_chat_ids');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_file_routes', function (Blueprint $table): void {
            $table->dropColumn('listener_key');
        });
    }
};
