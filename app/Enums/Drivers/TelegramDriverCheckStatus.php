<?php

namespace App\Enums\Drivers;

enum TelegramDriverCheckStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Confirmed = 'confirmed';
    case NotConfirmed = 'not_confirmed';
}