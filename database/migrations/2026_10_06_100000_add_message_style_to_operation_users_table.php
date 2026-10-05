<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a person is written to: in which language, and whether with the
 * respect owed to someone older.
 *
 * language is null until somebody picks one: then the role decides -
 * Russian for sales, Uzbek for operators (OperationUser::messageLanguage()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('role');
            $table->boolean('respectful')->default(false)->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn(['language', 'respectful']);
        });
    }
};
