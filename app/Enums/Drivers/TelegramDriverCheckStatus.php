<?php

namespace App\Enums\Drivers;

enum TelegramDriverCheckStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case NotConfirmed = 'not_confirmed';

    /**
     * A message that was recorded but carries nothing to check: a driver
     * update that did not touch the phone, or one whose check was re-run
     * on the original record instead.
     */
    case Skipped = 'skipped';

    public function isFinal(): bool
    {
        return $this === self::Confirmed
            || $this === self::NotConfirmed;
    }
}
