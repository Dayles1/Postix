<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->integer('interval')->nullable()->after('message_text'); 
            $table->integer('total_batches')->default(1)->after('interval'); 
            $table->integer('current_batch')->default(0)->after('total_batches'); 
        });
    }

    public function down(): void
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->dropColumn(['interval', 'total_batches', 'current_batch']);
        });
    }
};