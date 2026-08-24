<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_account_processes', function (Blueprint $table) {
            $table->boolean('is_busy')
                ->default(false)
                ->after('is_available');

            $table->timestamp('busy_at')
                ->nullable()
                ->after('is_busy');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_account_processes', function (Blueprint $table) {
            $table->dropColumn([
                'is_busy',
                'busy_at',
            ]);
        });
    }
};