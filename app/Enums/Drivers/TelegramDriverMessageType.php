<?php

namespace App\Enums\Drivers;

enum TelegramDriverMessageType: string
{
    case CREATED_DRIVER = 'created_driver';

    case UPDATED_DRIVER = 'updated_driver';

    case CREATED_TRANSPORT = 'created_transport';

    case UPDATED_TRANSPORT = 'updated_transport';

    case UNKNOWN = 'unknown';
}