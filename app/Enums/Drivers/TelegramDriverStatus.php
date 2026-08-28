<?php 

namespace App\Enums\Drivers;

enum TelegramDriverStatus: string
{
    case UNKNOWN = 'unknown';
    case VALID = 'valid';
    case INVALID = 'invalid';
}