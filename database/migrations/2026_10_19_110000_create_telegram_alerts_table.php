<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What went wrong in the Telegram flows and was told to the developer: a
 * penalty that could not be delivered, a file nobody forwarded, a listener
 * that keeps dying. One row per problem; the same problem coming again
 * within the window only raises `occurrences` (DeveloperAlerts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_alerts', function (Blueprint $table) {
            $table->id();

            /* penalty_unreachable, penalty_forward_failed, file_forward_failed ... */
            $table->string('kind', 64)->index();

            /* kind + what it is about (a person, a file, a listener): the same one is the same problem. */
            $table->string('fingerprint', 190)->index();

            $table->string('title');
            $table->text('details')->nullable();
            $table->json('context')->nullable();

            /* The listener whose account could not do it; empty for Driver Check. */
            $table->string('listener_key', 32)->nullable();

            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            /* Told to the developer's chat when; empty with notify_error when it could not be. */
            $table->timestamp('notified_at')->nullable();
            $table->text('notify_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_alerts');
    }
};
