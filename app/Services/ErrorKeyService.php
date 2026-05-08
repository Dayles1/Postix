<?php

namespace App\Services;

class ErrorKeyService
{

    public function __construct() {}

    public function translateErrorKey(?string $errorKey): string
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
                ]);
            }

            if ($minutes > 0) {
                return __('messages.errors.' . $matches[1] . '_minutes', [
                    'minutes' => $minutes,
                ]);
            }

            return __('messages.errors.' . $matches[1] . '_seconds', [
                'seconds' => $seconds,
            ]);
        }

        $translated = __("messages.errors.$errorKey");

        return $translated !== "messages.errors.$errorKey"
            ? $translated
            : __('messages.errors.unknown_error');
    }
}
