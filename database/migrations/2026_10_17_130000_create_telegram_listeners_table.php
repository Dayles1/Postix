<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * More listeners next to Driver Check: each is one account running its own
 * MadelineProto event loop (CEO, Head Operation ...). The Driver Check
 * account itself stays where it is, in TELEGRAM_DRIVER_CHECK_ACCOUNT_ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_listeners', function (Blueprint $table) {
            $table->id();

            /* ceo, head_operation ...: names the process, its lock and the scheduled messages' sender. */
            $table->string('key', 32)->unique();
            $table->string('name');

            $table->foreignId('telegram_account_id')
                ->nullable()
                ->constrained('telegram_accounts')
                ->nullOnDelete();

            $table->boolean('is_enabled')->default(true);

            /* What it does: TelegramListener::DUTIES. */
            $table->json('duties')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_listeners');
    }
};
