<?php

declare(strict_types=1);

namespace App\Telegram;

use Throwable;

/**
 * Carries "the previous listener died, here is why" from the watchdog to the
 * listener that replaces it.
 *
 * The startup notification goes to Saved Messages, and only the listener can
 * send it: the watchdog has no MadelineProto session, because the whole point
 * of the single-instance lock is that exactly one process owns it. So the
 * watchdog writes the reason down and the next listener reports it together
 * with its own start message.
 *
 * Distinct from {@see TelegramListenerHealth}, which runs the other way: the
 * listener tells the watchdog why it is about to exit.
 */
final class TelegramRestartNotice
{
    public static function path(): string
    {
        return storage_path('app/telegram/driver-check-restart.json');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(array $payload): void
    {
        try {
            $directory = dirname(self::path());

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents(
                self::path(),
                (string) json_encode(
                    $payload + ['recorded_at' => date('c')],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );
        } catch (Throwable) {
            /*
             * A missing notice only costs a line in the start message, so it
             * must never interfere with getting the listener back up.
             */
        }
    }

    /**
     * Read the notice and remove it, so one crash is reported exactly once.
     *
     * @return array<string, mixed>|null
     */
    public static function take(): ?array
    {
        try {
            if (!is_file(self::path())) {
                return null;
            }

            $raw = @file_get_contents(self::path());

            self::clear();

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
