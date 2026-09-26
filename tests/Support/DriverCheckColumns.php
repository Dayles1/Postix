<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * The project migrations are MySQL-only, so the tests build the tables
 * they need by hand. These are the telegram_driver_checks columns every
 * save touches (see TelegramDriverCheck::trackVerdictChange()), shared so
 * each hand-made table keeps up with the real one.
 */
final class DriverCheckColumns
{
    public static function lifecycle(Blueprint $table): void
    {
        $table->string('system_status')->nullable();
        $table->bigInteger('manual_by_telegram_id')->nullable();
        $table->string('manual_by_name')->nullable();
        $table->timestamp('manual_at')->nullable();
        $table->bigInteger('report_message_id')->nullable();
        $table->bigInteger('report_reply_to_message_id')->nullable();
        $table->timestamp('report_dirty_at')->nullable();
        $table->bigInteger('bot_message_id')->nullable();
    }
}
