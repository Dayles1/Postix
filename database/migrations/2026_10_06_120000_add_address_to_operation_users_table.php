<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a person is called, per language: {"uz": "Ali aka", "ru": "Али ака"}.
 *
 * Age and closeness decide it - "aka" for someone older, "jigar" for a
 * close friend, "uka" for someone younger - so it is written per person,
 * not worked out. Phrases take it as {address}; null leaves it out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->json('address')->nullable()->after('respectful');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
