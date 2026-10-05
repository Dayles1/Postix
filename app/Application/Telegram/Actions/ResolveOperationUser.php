<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Models\Telegram\OperationUser;

final class ResolveOperationUser
{
    /**
     * The role is only used when the person has to be created: an existing
     * row is matched by name alone and keeps the role it has.
     */
    public function execute(
        string $name,
        string $role = OperationUser::ROLE_OPERATION,
    ): OperationUser {
        $name = trim($name);

        /*
         * Normalisation lives on the model so an operator created by hand on
         * the operators page produces the exact same key as one created here
         * from a parsed message - otherwise the two would never meet.
         */
        $normalized = OperationUser::normalizeName($name);

        return OperationUser::query()->firstOrCreate(
            [
                'name_normalized' => $normalized,
            ],
            [
                'name' => $name,
                'role' => $role,
            ],
        );
    }
}
