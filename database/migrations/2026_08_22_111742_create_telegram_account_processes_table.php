<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_account_processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_account_id')
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();

            // resolver_phone, send_message, driver_check, etc.
            $table->string('process', 100);

            // Общая статистика
            $table->unsignedInteger('successes')->default(0);
            $table->unsignedInteger('failures')->default(0);

            // Ошибки подряд
            $table->unsignedInteger('consecutive_failures')->default(0);

            // Можно ли использовать аккаунт для этого процесса
            $table->boolean('is_available')->default(true);

            // Почему и когда отключили
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();

            // Дополнительные данные конкретного процесса
            $table->json('meta')->nullable();

            $table->timestamps();

            // Один процесс на один аккаунт
            $table->unique([
                'telegram_account_id',
                'process',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_account_processes');
    }
};