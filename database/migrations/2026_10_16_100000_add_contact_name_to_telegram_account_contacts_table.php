<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The name a person was saved under: with a base nick set in the panel the
 * contacts are "base", "base-1", "base-2" ..., and the next number is counted
 * from these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_account_contacts', function (Blueprint $table) {
            $table->string('contact_name', 120)->nullable()->after('error');

            $table->index(['telegram_account_id', 'contact_name']);
        });
    }

    public function down(): void
    {
        Schema::table('telegram_account_contacts', function (Blueprint $table) {
            $table->dropIndex(['telegram_account_id', 'contact_name']);
            $table->dropColumn('contact_name');
        });
    }
};
