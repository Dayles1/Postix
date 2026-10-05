<?php

namespace App\Enums\Telegram;

enum TelegramClientCheckStatus: string
{
    case Pending = 'pending';

    /**
     * Forwarded; the batch comment is still to come.
     */
    case Forwarded = 'forwarded';

    /**
     * Forwarded and commented.
     */
    case Sent = 'sent';

    /**
     * Telegram refused; retried while the penalty is still fresh.
     */
    case Failed = 'failed';

    /**
     * Nothing to deliver to: no responsible person, or one without a
     * Telegram contact. Still counts towards that person's level.
     */
    case Skipped = 'skipped';
}
