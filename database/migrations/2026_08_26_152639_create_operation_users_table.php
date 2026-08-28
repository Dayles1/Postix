<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up(): void
    {
        Schema::create('operation_users', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('name_normalized')->index();

            $table->string('telegram_username')->nullable()->index();
            $table->unsignedBigInteger('telegram_id')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_users');
    }
};