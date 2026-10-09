<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's own answers (PersonalAnswers): a voice message with their
 * name for the first penalty, a "Доброе утро" of their own - added to what
 * everyone gets, or instead of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->json('personal_answers')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('operation_users', function (Blueprint $table) {
            $table->dropColumn('personal_answers');
        });
    }
};
