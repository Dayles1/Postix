<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('limits', function (Blueprint $table) {
            $table->id();

            // Kimga tegishli
            $table->morphs('limitable');
            // limitable_type, limitable_id

            // Limitlar
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_phones')->nullable();
            $table->unsignedInteger('max_operations')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('limits');
    }
};
