<?php

use App\Enums\Drivers\TelegramDriverCheckReason;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_driver_checks', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('telegram_chat_id');
            $table->bigInteger('telegram_message_id');

            $table->text('message_text')->nullable();

            $table->string('phone_raw')->nullable();
            $table->string('phone_normalized')->nullable();

            $table->string('driver_name')->nullable();

            $table->bigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();

            $table->string('status');
            $table->string('reason')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();

            $table->json('telegram_raw')->nullable();

            $table->timestamp('checked_at')->nullable();

            $table->timestamps();

            $table->index('telegram_chat_id');
            $table->index('telegram_message_id');
            $table->index('phone_normalized');
            $table->index('telegram_user_id');
            $table->index('status');
            $table->index('reason');
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_driver_checks');
    }
};