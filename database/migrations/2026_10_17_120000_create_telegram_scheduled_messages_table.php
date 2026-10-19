<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Messages the listener's account writes into groups by itself, at a set
 * time on set days ("Отчет эсдан чикмасин" at 17:00): the panel's
 * Scheduled messages page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_scheduled_messages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('text');

            /* HH:MM, in the application's time zone. */
            $table->string('send_at', 5);

            /* ISO weekdays, 1 (Monday) to 7 (Sunday); empty is every day. */
            $table->json('days')->nullable();

            /* Telegram ids of the groups. */
            $table->json('chat_ids')->nullable();

            /* The listener it is written from (telegram_listeners.key); empty is the Driver Check account. */
            $table->string('listener_key', 32)->nullable();

            $table->boolean('is_active')->default(true);

            /* The day it last went out on schedule: one a day, however often the listener looks. */
            $table->date('last_sent_on')->nullable();

            /* "Send now" in the panel: only the listener owns the session, it answers within seconds. */
            $table->timestamp('send_requested_at')->nullable();

            /* The last try, scheduled or by hand, and how it ended. */
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 16)->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_scheduled_messages');
    }
};
