<?php

namespace App\Enums\Telegram;

enum TelegramAccountProcess: string
{
    case ResolverPhone = 'resolver_phone';
    case SendMessage = 'send_message';
    case DriverCheck = 'driver_check';
}