<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the person wrote back after a penalty, and what we answered.
 *
 * Kept on the person's latest penalty: their last message, the reply kind
 * it was read as (ClientCheckRules replies, null when none matched) and
 * the answer sent - one answer per penalty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->text('reply_text')->nullable()->after('sent_at');
            $table->timestamp('replied_at')->nullable()->after('reply_text');
            /*
             * The kind's name as it was then: kinds are renamed and
             * reordered in the panel, so an index would drift.
             */
            $table->string('reply_kind', 60)->nullable()->after('replied_at');
            $table->text('reply_answer')->nullable()->after('reply_kind');
            $table->timestamp('reply_answered_at')->nullable()->after('reply_answer');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_client_checks', function (Blueprint $table) {
            $table->dropColumn([
                'reply_text',
                'replied_at',
                'reply_kind',
                'reply_answer',
                'reply_answered_at',
            ]);
        });
    }
};
