<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            $table->string('type')
                ->after('telegram_message_id');

            $table->foreignId('driver_id')
                ->nullable()
                ->after('driver_name')
                ->constrained('telegram_drivers')
                ->nullOnDelete();

            $table->foreignId('operation_user_id')
                ->nullable()
                ->after('driver_id')
                ->constrained('operation_users')
                ->nullOnDelete();

            $table->foreignId('telegram_resolved_phone_id')
                ->nullable()
                ->after('operation_user_id')
                ->constrained('telegram_resolved_phones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_driver_checks', function (Blueprint $table) {
            $table->dropForeign([
                'driver_id',
            ]);

            $table->dropForeign([
                'operation_user_id',
            ]);

            $table->dropForeign([
                'telegram_resolved_phone_id',
            ]);

            $table->dropColumn([
                'type',
                'driver_id',
                'operation_user_id',
                'telegram_resolved_phone_id',
            ]);
        });
    }
};