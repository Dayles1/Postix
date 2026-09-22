<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The groups the driver-check listener watches.
 *
 * Until now there was exactly one, pinned in TELEGRAM_DRIVER_CHECK_CHAT_LINK.
 * Moving the list into the database is what makes it both optional (no row =
 * the listener idles instead of refusing to start) and editable without a
 * deploy: the running listener re-reads this table on a cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_driver_check_chats', function (Blueprint $table) {
            $table->id();

            /*
             * What was typed in: an invite link, a @username, or a raw chat
             * id. It is handed to MadelineProto as-is to be resolved.
             */
            $table->string('link')->nullable()->unique();

            /*
             * The resolved peer. Everything downstream - the incoming filter,
             * the report reply, telegram_driver_checks.telegram_chat_id -
             * works on this number.
             */
            $table->bigInteger('chat_id')->nullable()->unique();

            $table->string('title')->nullable();

            $table->boolean('is_active')->default(true);

            /*
             * 'env' rows are imported from TELEGRAM_DRIVER_CHECK_CHAT_LINK so
             * an existing deployment keeps working untouched; 'manual' rows
             * come from the panel.
             */
            $table->string('source', 16)->default('manual');

            $table->timestamp('resolved_at')->nullable();
            $table->text('resolve_error')->nullable();

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_driver_check_chats');
    }
};
