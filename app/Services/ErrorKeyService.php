<?php

namespace App\Services;

class ErrorKeyService
{

    public function __construct() {}

    public function translateErrorKey(?string $errorKey, ?string $locale = null): string
    {
        if (!$errorKey) {
            return '';
        }

        if (preg_match('/^(slowmode_wait|flood_wait)_(\d+)$/', $errorKey, $matches)) {
            $totalSeconds = (int) $matches[2];
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;

            if ($minutes > 0 && $seconds > 0) {
                return __('messages.errors.' . $matches[1] . '_minutes_seconds', [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                ], $locale);
            }

            if ($minutes > 0) {
                return __('messages.errors.' . $matches[1] . '_minutes', [
                    'minutes' => $minutes,
                ], $locale);
            }

            return __('messages.errors.' . $matches[1] . '_seconds', [
                'seconds' => $seconds,
            ], $locale);
        }

        $translated = __("messages.errors.$errorKey", [], $locale);

        if ($translated !== "messages.errors.$errorKey") {
            return $translated;
        }

        // Untranslated Telegram RPC code (or raw error text from older rows) —
        // show it as-is rather than "unknown error"
        $code = preg_match('/^[a-z0-9_]+$/', $errorKey) ? strtoupper($errorKey) : $errorKey;

        return __('messages.errors.telegram_error', ['code' => $code], $locale);
    }
}
