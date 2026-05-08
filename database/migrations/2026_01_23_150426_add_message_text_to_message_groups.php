<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->text('message_text')->nullable()->after('id');
        });
    }

    public function down()
    {
        Schema::table('message_groups', function (Blueprint $table) {
            $table->dropColumn('message_text');
        });
    }
};
