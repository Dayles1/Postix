<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            $table->timestamp('reported_at')
                ->nullable()
                ->after('checked_at');

            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            $table->dropIndex([
                'telegram_driver_checks_reported_at_index',
            ]);

            $table->dropColumn('reported_at');
        });
    }
};