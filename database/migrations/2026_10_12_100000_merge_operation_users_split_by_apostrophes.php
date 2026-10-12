<?php

use App\Models\Telegram\OperationUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The name key used to keep apostrophes, so "O`GLI" from a message and
 * "OGLI" typed on the operators page became two people: the checks and
 * penalties on one, the Telegram contact on the other. The key is rebuilt
 * and every such pair is folded into the row that can be messaged.
 */
return new class extends Migration
{
    private const REFERENCING_TABLES = [
        'telegram_drivers',
        'telegram_driver_checks',
        'telegram_client_checks',
    ];

    private const CONTACT_FIELDS = [
        'telegram_username',
        'telegram_id',
        'language',
        'respectful',
        'address',
        'personal_answers',
        'dm_last_sent_at',
        'last_private_message_at',
    ];

    public function up(): void
    {
        $groups = DB::table('operation_users')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (object $row): string => OperationUser::normalizeName((string) $row->name));

        DB::transaction(function () use ($groups): void {
            foreach ($groups as $key => $rows) {
                $keeper = $rows->first(fn (object $row): bool => $this->reachable($row)) ?? $rows->first();
                $others = $rows->reject(fn (object $row): bool => $row->id === $keeper->id);

                $fill = [];

                foreach ($others as $other) {
                    foreach (self::CONTACT_FIELDS as $field) {
                        if (! property_exists($other, $field) || $this->filled($keeper->{$field} ?? null)) {
                            continue;
                        }

                        if ($this->filled($other->{$field}) && ! array_key_exists($field, $fill)) {
                            $fill[$field] = $other->{$field};
                        }
                    }

                    foreach (self::REFERENCING_TABLES as $table) {
                        DB::table($table)
                            ->where('operation_user_id', $other->id)
                            ->update(['operation_user_id' => $keeper->id]);
                    }

                    DB::table('operation_users')->where('id', $other->id)->delete();
                }

                DB::table('operation_users')
                    ->where('id', $keeper->id)
                    ->update(['name_normalized' => $key] + $fill);
            }
        });
    }

    public function down(): void
    {
        //
    }

    private function reachable(object $row): bool
    {
        return $this->filled(ltrim(trim((string) $row->telegram_username), '@'))
            || ($row->telegram_id ?? 0) > 0;
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== '[]';
    }
};
