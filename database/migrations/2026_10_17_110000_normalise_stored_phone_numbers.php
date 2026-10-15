<?php

use App\Models\Telegram\TelegramAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Phone numbers stored in one form, +<country code><number>: the accounts'
 * and the people found in groups. New rows get it from the models; this
 * brings the old ones in line. A row whose normal form is already taken by
 * another (the accounts' phone is unique) is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['telegram_accounts', 'telegram_scraped_users'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'phone')) {
                continue;
            }

            DB::table($table)->whereNotNull('phone')->orderBy('id')->each(function (object $row) use ($table): void {
                $normal = TelegramAccount::normalizePhone((string) $row->phone);
                $normal = $normal === '' ? null : $normal;

                if ($normal === $row->phone) {
                    return;
                }

                try {
                    DB::table($table)->where('id', $row->id)->update(['phone' => $normal]);
                } catch (Throwable) {
                    // taken by another row: left as it is
                }
            });
        }
    }

    public function down(): void
    {
        // The old spellings are not kept.
    }
};
