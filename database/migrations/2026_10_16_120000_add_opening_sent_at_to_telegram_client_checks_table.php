<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which penalty carried our greeting ("Assalomu alaykum, Ali aka"). The
 * "greeted today" mark lives in the cache and a cache:clear or a deploy
 * empties it; this row survives, so the second greeting of a day is not sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->timestamp('opening_sent_at')->nullable()->after('forwarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->dropColumn('opening_sent_at');
        });
    }
};
