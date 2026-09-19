<?php

namespace App\Application\Telegram\Services;

use Amp\CancelledException;
use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramContactResolver
{
    /**
     * @param array<string, mixed> $context Diagnostic context from the caller
     *                                      (check_id, attempt, account_id,
     *                                      account_phone). Optional, never
     *                                      affects the resolve behaviour.
     */
    public function resolve(
        API $api,
        string $phone,
        array $context = [],
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
            /*
             * An Amp cancellation collapses into the useless message
             * "The operation was cancelled". The primary cause - usually
             * Amp\TimeoutException with the actual MTProto method name - only
             * lives in getPrevious(), so it is captured here before the
             * exception is discarded.
             */
            $diagnostics = $this->isCancellation($e)
                ? $this->cancellationDiagnostics($e, $context)
                : [];

            if ($diagnostics !== []) {
                Log::critical(
                    'Telegram contact resolver cancelled',
                    $diagnostics,
                );
            }

            return [
                'success' => false,

                'reason' => 'telegram_error',

                'user' => null,

                'raw' => null,

                'error_message' =>
                    $e->getMessage(),
            ] + $diagnostics;
        }
    }

    /**
     * Whether this Throwable, or anything in its previous chain, is an Amp
     * cancellation.
     */
    private function isCancellation(
        Throwable $e,
    ): bool {
        $current = $e;
        $depth = 0;

        while ($current !== null && $depth < 10) {
            if ($current instanceof CancelledException) {
                return true;
            }

            $class = mb_strtolower($current::class);
            $message = mb_strtolower($current->getMessage());

            if (
                str_contains($class, 'cancelledexception')
                || str_contains($message, 'operation was cancelled')
            ) {
                return true;
            }

            $current = $current->getPrevious();
            $depth++;
        }

        return false;
    }

    /**
     * Full diagnostic payload for a cancelled resolve.
     *
     * Returned to the caller as extra result keys, so the command can log the
     * same data next to its own check/attempt context.
     *
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function cancellationDiagnostics(
        Throwable $e,
        array $context,
    ): array {
        $previous = $e->getPrevious();

        return [
            'check_id' =>
                $context['check_id'] ?? null,

            'attempt' =>
                $context['attempt'] ?? null,

            'account_id' =>
                $context['account_id'] ?? null,

            'account_phone' =>
                $context['account_phone'] ?? null,

            'pid' =>
                getmypid(),

            'diagnosed_at' =>
                date('c'),

            'exception' =>
                $e::class,

            'exception_class' =>
                $e::class,

            'exception_message' =>
                $e->getMessage(),

            'exception_code' =>
                $e->getCode(),

            'exception_file' =>
                $e->getFile(),

            'exception_line' =>
                $e->getLine(),

            'exception_trace' =>
                $this->redact($e->getTraceAsString()),

            /*
             * The real cause: CancelledException("The operation was
             * cancelled") wraps TimeoutException("Timeout while waiting for
             * contacts.resolvePhone").
             */
            'previous_exception' =>
                $previous !== null
                    ? $previous::class
                    : null,

            'previous_message' =>
                $previous?->getMessage(),

            'previous_code' =>
                $previous?->getCode(),

            'previous_file' =>
                $previous?->getFile(),

            'previous_line' =>
                $previous?->getLine(),

            'previous_trace' =>
                $previous !== null
                    ? $this->redact($previous->getTraceAsString())
                    : null,
        ];
    }

    /**
     * Strip key material from a stack trace.
     *
     * zend.exception_ignore_args is Off on this server, so traces contain
     * scalar call arguments. Frames, files and lines are kept intact; only the
     * api_hash and long hex blobs (auth keys, hashes) are masked.
     */
    private function redact(
        string $trace,
    ): string {
        $apiHash = (string) config('services.telegram.api_hash');

        if ($apiHash !== '') {
            $trace = str_replace(
                $apiHash,
                '[REDACTED_API_HASH]',
                $trace,
            );
        }

        return (string) preg_replace(
            '/\b[0-9a-fA-F]{32,}\b/',
            '[REDACTED_HEX]',
            $trace,
        );
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