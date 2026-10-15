<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/*
 * The Uzbek texts saved in the panel go out in Cyrillic now (UzbekScript),
 * and the panel keeps them that way. This rewrites what was saved before.
 * Run before telegram:merge-defaults: the shipped texts are Cyrillic too, so
 * the two then match instead of sitting side by side.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Artisan::call('telegram:cyrillic-uz');
        } catch (Throwable $e) {
            /* A texts problem must not stop the schema from migrating; run the command by hand. */
            Log::warning('telegram:cyrillic-uz failed in the migration', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // Latin cannot be told from Cyrillic-by-choice any more; nothing to undo.
    }
};
