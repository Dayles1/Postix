<?php

use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Models\Telegram\TelegramSetting;
use Illuminate\Database\Migrations\Migration;

/*
 * More phrases per level (2026-10-09): the same "Nima qilay, boshqaga
 * olaymi?" on every fourth penalty reads like a bot. The new defaults of
 * config/client_checks.php are added to the rules saved in the panel -
 * to the level with the same `from`, after what is there, never twice.
 * Only the new ones: the first phrase of each set is the one shipped
 * before, and if it was removed in the panel it stays removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $saved = TelegramSetting::get(TelegramSetting::CLIENT_CHECK_RULES);

        if (! is_array($saved)) {
            return;
        }

        $rules = ClientCheckRules::fromArray($saved)->toArray();
        $defaults = app(ClientCheckRulesStore::class)->defaults()->toArray();

        foreach ($rules['roles'] as $role => &$ladder) {
            foreach ($ladder['levels'] as &$level) {
                $default = collect($defaults['roles'][$role]['levels'] ?? [])->firstWhere('from', $level['from']);

                if ($default === null) {
                    continue;
                }

                foreach ($default['phrases'] as $language => $tones) {
                    foreach ($tones as $tone => $phrases) {
                        $current = $level['phrases'][$language][$tone] ?? [];

                        $level['phrases'][$language][$tone] = array_slice(
                            array_values(array_unique([...$current, ...array_slice($phrases, 1)])),
                            0,
                            30,
                        );
                    }
                }
            }
            unset($level);
        }
        unset($ladder);

        TelegramSetting::set(TelegramSetting::CLIENT_CHECK_RULES, $rules);
    }

    public function down(): void
    {
        //
    }
};
