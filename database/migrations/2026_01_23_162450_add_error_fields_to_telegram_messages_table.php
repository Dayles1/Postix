<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->text('message_text')->nullable()->change();

            $table->string('error_key', 100)->nullable()->after('attempts');
            $table->index('error_key');
        });
    }

    public function down()
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->text('message_text')->nullable(false)->change();

            $table->dropIndex(['error_key']);
            $table->dropColumn('error_key');
        });
    }
};
