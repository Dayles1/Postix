<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operation or Sales.
 *
 * The CRM penalty bot names either kind of employee as the one responsible.
 * For us both are the same thing - a person found by name and written to in
 * Telegram - so they share the table; the role only tells them apart.
 * Every existing row came from the driver-check flow and is an operator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->string('role', 16)
                ->default('operation')
                ->after('name_normalized');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
