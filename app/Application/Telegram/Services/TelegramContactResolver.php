<?php

namespace App\Application\Telegram\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use Throwable;

class TelegramContactResolver
{
    public function resolve(
        API $api,
        string $phone,
    ): array {
        try {
            $result = $api->contacts->resolvePhone(
                phone: $phone
            );

            $user = $result['users'][0] ?? null;

            if (! $user) {
                return [
                    'success' => false,

                    'reason' =>
                        'telegram_not_registered',

                    'user' => null,

                    'raw' => $result,

                    'error_message' => null,
                ];
            }

            return [
                'success' => true,

                'reason' => null,

                'user' => $user,

                'raw' => $result,

                'error_message' => null,
            ];
        } catch (RPCErrorException $e) {
            return [
                'success' => false,

                'reason' =>
                    $this->mapError($e),

                'user' => null,

                'raw' => null,

                'error_message' =>
                    $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,

                'reason' => 'telegram_error',

                'user' => null,

                'raw' => null,

                'error_message' =>
                    $e->getMessage(),
            ];
        }
    }

    private function mapError(
        Throwable $exception
    ): string {
        $message = strtoupper(
            $exception->getMessage()
        );

        if (
            str_contains($message, 'FLOOD_WAIT')
            || str_contains($message, 'FLOOD')
        ) {
            return 'telegram_resolve_flood';
        }

        if (
            str_contains(
                $message,
                'PHONE_NOT_OCCUPIED'
            )
            || str_contains(
                $message,
                'PHONE_NUMBER_UNOCCUPIED'
            )
        ) {
            return 'telegram_not_registered';
        }

        return 'telegram_error';
    }
}