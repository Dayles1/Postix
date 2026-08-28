<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Models\Driver\TelegramDriver;
use App\Models\Telegram\OperationUser;
use Illuminate\Support\Str;

final class ResolveTelegramDriver
{
    public function execute(
        OperationUser $operationUser,
        array $data,
    ): TelegramDriver {
        $name = trim(
            (string) ($data['driver_name'] ?? ''),
        );

        $normalizedName = $this->normalize($name);

        return TelegramDriver::query()->firstOrCreate(
            [
                'operation_user_id' => $operationUser->id,
                'name_normalized' => $normalizedName,
            ],
            [
                'name' => $name,
                'status' => 'pending',
            ],
        );
    }

    private function normalize(
        string $name,
    ): string {
        $name = preg_replace(
            '/\s+/u',
            ' ',
            trim($name),
        ) ?? trim($name);

        return Str::upper($name);
    }
}