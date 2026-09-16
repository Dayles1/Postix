<?php

namespace App\Application\Telegram\Services;

use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramDriverCheckLogger
{
    public function created(TelegramDriverCheck $check): void
    {
        Log::info('Telegram driver check created', [
            'check_id' => $check->id,
            'chat_id' => $check->telegram_chat_id,
            'message_id' => $check->telegram_message_id,
            'phone' => $check->phone_normalized,
            'driver_name' => $check->driver_name,
        ]);
    }

    public function resolveStarted(TelegramDriverCheck $check): void
    {
        Log::debug('Telegram driver phone resolve started', [
            'check_id' => $check->id,
            'phone' => $check->phone_normalized,
        ]);
    }

    public function resolved(
        TelegramDriverCheck $check,
        array $user,
    ): void {
        Log::info('Telegram driver phone resolved', [
            'check_id' => $check->id,
            'telegram_user_id' => $user['id'] ?? null,
            'telegram_first_name' => $user['first_name'] ?? null,
            'telegram_last_name' => $user['last_name'] ?? null,
            'telegram_username' => $user['username'] ?? null,
        ]);
    }

    public function nameMatched(
        TelegramDriverCheck $check,
        array $match,
    ): void {
        Log::info('Telegram driver name matched', [
            'check_id' => $check->id,
            'score' => $match['score'] ?? null,
            'expected_name' => $match['expected_name'] ?? null,
            'telegram_name' => $match['telegram_name'] ?? null,
        ]);
    }

    public function nameMismatch(
        TelegramDriverCheck $check,
        array $match,
    ): void {
        Log::warning('Telegram driver name mismatch', [
            'check_id' => $check->id,
            'score' => $match['score'] ?? null,
            'expected_name' => $match['expected_name'] ?? null,
            'telegram_name' => $match['telegram_name'] ?? null,
        ]);
    }

    public function failed(
        TelegramDriverCheck $check,
        Throwable $exception,
    ): void {
        Log::error('Telegram driver check failed', [
            'check_id' => $check->id,
            'phone' => $check->phone_normalized,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}