<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            /*
             * The verdict the system reached, kept apart from `status` so
             * a manual decision can be shown next to it (struck through)
             * instead of silently replacing it.
             */
            $table->string('system_status')
                ->nullable()
                ->after('status');

            /*
             * Who overrode the verdict from the group, with the bot's
             * button. Null while the system's verdict stands.
             */
            $table->bigInteger('manual_by_telegram_id')
                ->nullable()
                ->after('system_status');

            $table->string('manual_by_name')
                ->nullable()
                ->after('manual_by_telegram_id');

            $table->timestamp('manual_at')
                ->nullable()
                ->after('manual_by_name');

            /*
             * The report MadelineProto posted, so it can be edited in
             * place when the verdict changes, and the message it goes
             * under when that is not the driver message itself (a phone
             * change is answered under the change).
             */
            $table->bigInteger('report_message_id')
                ->nullable()
                ->after('reported_at');

            $table->bigInteger('report_reply_to_message_id')
                ->nullable()
                ->after('report_message_id');

            /*
             * Set when the verdict changed after it was reported; the
             * listener edits the report and clears it.
             */
            $table->timestamp('report_dirty_at')
                ->nullable()
                ->after('report_reply_to_message_id');

            /*
             * The bot's message with the confirm / recheck buttons.
             */
            $table->bigInteger('bot_message_id')
                ->nullable()
                ->after('report_dirty_at');

            $table->index('report_dirty_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            $table->dropIndex(['report_dirty_at']);

            $table->dropColumn([
                'system_status',
                'manual_by_telegram_id',
                'manual_by_name',
                'manual_at',
                'report_message_id',
                'report_reply_to_message_id',
                'report_dirty_at',
                'bot_message_id',
            ]);
        });
    }
};
