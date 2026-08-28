<?php 

namespace App\Enums\Drivers;

enum TelegramDriverCheckType: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
}