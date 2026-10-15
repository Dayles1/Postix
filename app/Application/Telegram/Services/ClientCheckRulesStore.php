<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramSetting;

/**
 * Where the penalty rules live: in the database once saved from the panel,
 * config/client_checks.php until then.
 *
 * Read on every use, never cached - the listener is a long-running process
 * and a rule changed in the panel applies from the next penalty on.
 */
final class ClientCheckRulesStore
{
    public function current(): ClientCheckRules
    {
        $saved = TelegramSetting::get(TelegramSetting::CLIENT_CHECK_RULES);

        return is_array($saved)
            ? ClientCheckRules::fromArray($saved)
            : $this->defaults();
    }

    /**
     * What "reset" goes back to.
     */
    public function defaults(): ClientCheckRules
    {
        return ClientCheckRules::fromConfig((array) config('client_checks', []));
    }

    public function isCustomised(): bool
    {
        return is_array(TelegramSetting::get(TelegramSetting::CLIENT_CHECK_RULES));
    }

    public function save(ClientCheckRules $rules): void
    {
        /* Uzbek phrases are kept in Cyrillic, as the person gets them (UzbekScript). */
        $data = UzbekScript::enabled() ? UzbekScript::penalties($rules->toArray()) : $rules->toArray();

        TelegramSetting::set(TelegramSetting::CLIENT_CHECK_RULES, $data);
    }

    public function reset(): void
    {
        TelegramSetting::query()
            ->where('key', TelegramSetting::CLIENT_CHECK_RULES)
            ->delete();
    }
}
