<?php

declare(strict_types=1);

namespace App\Telegram;

use Throwable;

/**
 * Advisory flock-based single-instance guard for long-running Telegram
 * processes.
 *
 * A MadelineProto session may only be owned by one full instance: a second
 * process silently degrades to an IPC client and API::reconnectFull() then
 * bails out with "the bot is already running", which looks like a clean
 * exit code 0 to the watchdog.
 *
 * The lock is held by the OS for as long as the process lives, so a crashed or
 * SIGKILLed process releases it automatically - nothing to clean up by hand.
 */
final class TelegramProcessLock
{
    /**
     * Handles are kept for the whole process lifetime on purpose: releasing
     * the resource would release the lock.
     *
     * @var array<string, resource>
     */
    private static array $handles = [];

    /**
     * The lock is ours.
     */
    public const ACQUIRED = 'acquired';

    /**
     * Another live process holds the lock.
     */
    public const HELD = 'held_by_other';

    /**
     * The lock file could not be created or opened (permissions, read-only
     * filesystem, missing storage directory).
     *
     * This is NOT a reason to refuse to start: the guard is an optimisation,
     * and treating it as "already running" would block a restart over a
     * filesystem problem.
     */
    public const UNAVAILABLE = 'unavailable';

    private static ?string $lastError = null;

    /**
     * @return self::ACQUIRED|self::HELD|self::UNAVAILABLE
     */
    public static function attempt(string $name): string
    {
        self::$lastError = null;

        try {
            $path = self::path($name);
            $directory = dirname($path);

            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                self::$lastError = "cannot create directory {$directory}";

                return self::UNAVAILABLE;
            }

            $handle = @fopen($path, 'c+');

            if ($handle === false) {
                self::$lastError = "cannot open lock file {$path}: "
                    . (error_get_last()['message'] ?? 'unknown error');

                return self::UNAVAILABLE;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                self::$lastError = 'lock is held by another process';

                return self::HELD;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) getmypid());
            fflush($handle);

            self::$handles[$name] = $handle;

            return self::ACQUIRED;
        } catch (Throwable $e) {
            self::$lastError = $e::class . ': ' . $e->getMessage();

            return self::UNAVAILABLE;
        }
    }

    /**
     * Why the last attempt() did not acquire the lock.
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function acquire(string $name): bool
    {
        return self::attempt($name) === self::ACQUIRED;
    }

    /**
     * Does this process already hold the lock?
     */
    public static function holds(string $name): bool
    {
        return isset(self::$handles[$name]);
    }

    /**
     * Is another live process holding this lock right now?
     *
     * Asked by the watchdog before it spawns a listener: starting a process
     * whose only possible outcome is EXIT_ALREADY_RUNNING is a restart that
     * never had to happen. The lock is taken and released again straight away,
     * which is the only authoritative answer - a PID file can be stale, flock
     * cannot.
     *
     * A lock this process already owns is not "another process", and an
     * unusable lock file answers false so a broken guard can never stop the
     * listener from being started.
     */
    public static function isHeldByOtherProcess(string $name): bool
    {
        if (isset(self::$handles[$name])) {
            return false;
        }

        try {
            $path = self::path($name);

            if (!is_file($path)) {
                return false;
            }

            $handle = @fopen($path, 'c+');

            if ($handle === false) {
                return false;
            }

            /*
             * A probe, not a claim: the file is never written to, so the pid
             * recorded in it keeps pointing at the real owner instead of being
             * overwritten by every poll.
             */
            $free = flock($handle, LOCK_EX | LOCK_NB);

            if ($free) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);

            return !$free;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * PID currently recorded in the lock file, for diagnostics only.
     */
    public static function holderPid(string $name): ?int
    {
        try {
            $path = self::path($name);

            if (!is_file($path)) {
                return null;
            }

            /*
             * Best-effort diagnostics only. flock is advisory on Linux so the
             * read succeeds while the owner holds the lock, but on Windows the
             * lock is mandatory and the read is denied.
             */
            $raw = @file_get_contents($path);

            if ($raw === false) {
                return null;
            }

            $pid = (int) trim($raw);

            return $pid > 0
                ? $pid
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function release(string $name): void
    {
        $handle = self::$handles[$name] ?? null;

        if ($handle === null) {
            return;
        }

        unset(self::$handles[$name]);

        try {
            flock($handle, LOCK_UN);
            fclose($handle);
        } catch (Throwable) {
            //
        }
    }

    public static function path(string $name): string
    {
        return storage_path(
            'app/telegram/' . $name . '.lock'
        );
    }
}
