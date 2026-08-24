<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_resolved_phones', function (Blueprint $table): void {
            $table->id();

            /*
             * Нормализованный номер телефона.
             *
             * Например:
             * +998901234567
             */
            $table->string('phone_normalized', 32)->unique();

            /*
             * Telegram user information.
             */
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username', 255)->nullable();
            $table->string('telegram_first_name', 255)->nullable();
            $table->string('telegram_last_name', 255)->nullable();

            /*
             * Полный ответ Telegram resolvePhone().
             */
            $table->json('telegram_raw')->nullable();

            /*
             * Каким resolver account был найден пользователь.
             * Для истории и диагностики.
             */
            $table->foreignId('telegram_account_id')
                ->nullable()
                ->constrained('telegram_accounts')
                ->nullOnDelete();

            /*
             * Когда номер был успешно разрешён.
             */
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('telegram_user_id');
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_resolved_phones');
    }
};