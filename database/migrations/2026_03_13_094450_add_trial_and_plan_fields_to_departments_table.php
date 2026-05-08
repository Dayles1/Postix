<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {

            $table->enum('plan', ['trial', 'pro'])->default('pro')->after('name');
            $table->timestamp('trial_started_at')->nullable()->after('plan');
            $table->timestamp('trial_expires_at')->nullable()->after('trial_started_at');
            $table->timestamp('subscription_expires_at')->nullable()->after('trial_expires_at');
            $table->boolean('is_active')->default(true)->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'trial_started_at',
                'trial_expires_at',
                'subscription_expires_at',
                'is_active'
            ]);
        });
    }
};
