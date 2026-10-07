<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The nudge after a penalty nobody answered ("Рушана апа, илтимос"):
 * when it went out and what it said. Kept on the penalty that carried the
 * comment; set once, so a penalty is never nudged twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->timestamp('nudged_at')->nullable()->after('reply_answered_at');
            $table->text('nudge_text')->nullable()->after('nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->dropColumn(['nudged_at', 'nudge_text']);
        });
    }
};
