<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
 * More variants (2026-10-13): ten times the auto replies, "Это тоже", and
 * the texts taken from real chats ("обновите статус", "Сэнк ю!",
 * "обрабатываю", "Ассалому алекейкум"). The panel keeps its own copy of
 * both files, so the new ones are added to it (telegram:merge-defaults).
 * Later batches of variants: the same command, run after the deploy.
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
