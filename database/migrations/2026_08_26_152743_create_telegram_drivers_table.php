<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_drivers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('name_normalized')->index();

            $table->foreignId('operation_user_id')
                ->nullable()
                ->constrained('operation_users')
                ->nullOnDelete();

            $table->string('status')
                    ->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_drivers');
    }
};