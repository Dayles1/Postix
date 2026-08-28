<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Models\Telegram\OperationUser;
use Illuminate\Support\Str;

final class ResolveOperationUser
{
    public function execute(
        string $name,
    ): OperationUser {
        $name = trim($name);

        $normalized = $this->normalize($name);

        return OperationUser::query()->firstOrCreate(
            [
                'name_normalized' => $normalized,
            ],
            [
                'name' => $name,
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