<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
 * Fewer greetings and thanks (2026-10-23): the texts nobody here writes
 * ("Сэнк ю!", "Хайрли тонг", "Миннатдорман") are taken out of the copy
 * saved in the panel too (auto_replies.retired, telegram:merge-defaults).
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('telegram:merge-defaults');
    }

    public function down(): void
    {
        //
    }
};
