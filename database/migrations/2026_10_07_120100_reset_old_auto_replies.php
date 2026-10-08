<?php

use Illuminate\Database\Migrations\Migration;

/*
 * The auto replies saved before the single «Благодарность» kind (their
 * nudge still counted minutes) are set aside, so the new defaults apply.
 * The old file is kept next to it as a backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        $path = config('auto_replies.path');

        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        $rules = json_decode((string) file_get_contents($path), true);

        if (! is_array($rules) || ! array_key_exists('after_minutes', $rules['silence'] ?? [])) {
            return;
        }

        rename($path, $path . '.' . date('Ymd-His') . '.bak');
    }

    public function down(): void
    {
        //
    }
};
