<?php

declare(strict_types=1);

namespace App\Telegram;

use Throwable;

/**
 * Explains WHY the long-running Telegram listener stopped.
 *
 * The event handler lives inside MadelineProto's event loop, while the process
 * exit code is decided by TelegramDriverCheckCommand. This marker is the
 * channel between the two: without it a self-terminated listener is
 * indistinguishable from a graceful stop, and the watchdog cannot tell that it
 * must start a brand new process.
 */
final class TelegramListenerHealth
{
    /**
     * MadelineProto's generic update loop died and will never be resumed.
     */
    public const REASON_UPDATE_LOOP_DEAD = 'update_loop_dead';

    /**
     * onStart() could not bring the listener into a working state.
     */
    public const REASON_START_FAILED = 'start_failed';

    public static function path(): string
    {
        return storage_path('app/telegram/driver-check-health.json');
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function markUnhealthy(
        string $reason,
        array $context = []
    ): void {
        $payload = [
            'reason' => $reason,
            'pid' => getmypid(),
            'marked_at' => date('c'),
            'context' => $context,
        ];

        try {
            $directory = dirname(self::path());

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents(
                self::path(),
                (string) json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );
        } catch (Throwable) {
            /*
             * Never let diagnostics break the listener: the exit code path
             * falls back to "unexpected_return" when the marker is missing.
             */
        }
    }

    /**
     * Read the marker without consuming it.
     *
     * The listener process reads it to pick its own exit code; the watchdog
     * consumes it afterwards with take() to log the reason.
     *
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        try {
            if (!is_file(self::path())) {
                return null;
            }

            $raw = @file_get_contents(self::path());

            if ($raw === false) {
                return null;
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? $decoded
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the marker and remove it.
     *
     * @return array<string, mixed>|null
     */
    public static function take(): ?array
    {
        $marker = self::read();

        self::clear();

        return $marker;
    }

    public static function clear(): void
    {
        try {
            if (is_file(self::path())) {
                unlink(self::path());
            }
        } catch (Throwable) {
            //
        }
    }
}
