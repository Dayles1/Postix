<?php

namespace App\Enums\Drivers;

enum TelegramDriverCheckReason: string
{
    case TELEGRAM_NOT_REGISTERED = 'telegram_not_registered';

    case NAME_MISMATCH = 'name_mismatch';

    case TELEGRAM_RESOLVE_FLOOD = 'telegram_resolve_flood';

    case INVALID_PHONE = 'invalid_phone';

    case PHONE_NOT_FOUND_IN_MESSAGE = 'phone_not_found_in_message';

    case TELEGRAM_ERROR = 'telegram_error';
}