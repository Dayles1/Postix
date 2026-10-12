<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The morning files of the notifications bot ("Zitic — 2026-10-09",
 * "qozoq_..._tekshirilgan.xlsx"): which kind goes to which groups, and
 * every forward that was made, so nothing is sent twice and a refused
 * one is retried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_file_routes', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            /* Comma separated, any of them in the caption or file name. */
            $table->string('keywords', 500);

            /* Telegram ids of the groups the file is forwarded to. */
            $table->json('target_chat_ids')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();
        });

        Schema::create('telegram_file_forwards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('file_route_id')
                ->nullable()
                ->constrained('telegram_file_routes')
                ->nullOnDelete();

            $table->bigInteger('source_chat_id');
            $table->bigInteger('message_id');

            $table->string('file_name')->nullable();
            $table->string('caption', 1000)->nullable();

            /* Null when no route matched the file. */
            $table->bigInteger('target_chat_id')->nullable();

            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['source_chat_id', 'message_id', 'target_chat_id'],
                'telegram_file_forwards_unique',
            );

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_file_forwards');
        Schema::dropIfExists('telegram_file_routes');
    }
};
