<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When we first saw the person had read our comment: the nudge for
 * silence is counted from it, not from the comment.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table): void {
            $table->timestamp('seen_at')->nullable()->after('nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table): void {
            $table->dropColumn('seen_at');
        });
    }
};
