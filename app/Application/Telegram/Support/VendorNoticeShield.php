<?php

declare(strict_types=1);

namespace App\Application\Telegram\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a Telegram call with PHP notices raised *inside vendor code*
 * demoted from exceptions back to log lines.
 *
 * Laravel installs an error handler that turns every PHP warning and
 * notice into an ErrorException. That is the right default for our own
 * code -- a notice there is a bug worth stopping for -- but it hands a
 * third-party library a veto over work that has already succeeded.
 *
 * Driver check #882 is what this is for. The phone resolved, the name
 * matched at 96, the verdict was written, and then a notice from inside
 * MadelineProto ("Undefined property:
 * danog\MadelineProto\Exception::$class") became an exception, unwound
 * the command into its failure branch and turned a confirmed driver into
 * an unconfirmed one with a PHP message where the reason should be. The
 * check was correct and was thrown away over a library's internal
 * bookkeeping.
 *
 * So, for the duration of one call:
 *
 *  - a non-fatal diagnostic from a file under vendor/ is logged, with
 *    the frames that produced it, and execution continues -- the library
 *    is allowed to be sloppy, it is not allowed to decide the outcome;
 *  - anything raised by our own code is passed straight to the handler
 *    that was installed before, so Laravel still turns it into an
 *    exception and we still hear about our own bugs;
 *  - fatal errors are untouched, because they never reach a userland
 *    error handler in the first place.
 *
 * Logging is the point of the exercise, not a side effect: the notice
 * above does not exist anywhere in this codebase and does not exist in
 * the vendored MadelineProto either, so the next occurrence has to
 * arrive with its file, line and call frames attached for anyone to be
 * able to chase it upstream.
 */
final class VendorNoticeShield
{
    /**
     * Diagnostics a library is allowed to emit without killing the
     * operation. Everything else keeps its normal behaviour.
     */
    private const NON_FATAL = E_WARNING
        | E_NOTICE
        | E_DEPRECATED
        | E_USER_WARNING
        | E_USER_NOTICE
        | E_USER_DEPRECATED;

    /**
     * How many call frames are kept with a demoted notice. Enough to
     * name the library function and how we got into it; not the entire
     * async stack.
     */
    private const TRACE_FRAMES = 8;

    /**
     * What makes a file somebody else's code.
     *
     * "phar://" counts because MadelineProto is as often deployed as a
     * phar as it is installed through composer, and a notice from
     * inside the phar is exactly as much somebody else's business.
     *
     * @var list<string>
     */
    private const FOREIGN_PATH_MARKERS = [
        '/vendor/',
        'phar://',
    ];

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @param  array<string, mixed>  $context  what the caller was doing, for the log line
     * @return T
     */
    public static function guard(string $description, callable $operation, array $context = []): mixed
    {
        $previous = set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file = '',
                int $line = 0,
            ) use ($description, $context, &$previous): bool {
                if (self::isDemotable($severity, $file)) {
                    self::report($description, $severity, $message, $file, $line, $context);

                    return true;
                }

                /*
                 * Not ours to swallow: hand it back to whoever was
                 * handling errors before this call, so our own code
                 * keeps failing exactly as loudly as it did.
                 */
                return is_callable($previous)
                    ? (bool) $previous($severity, $message, $file, $line)
                    : false;
            },
        );

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private static function isDemotable(int $severity, string $file): bool
    {
        return ($severity & self::NON_FATAL) !== 0
            && self::isVendorFile($file);
    }

    private static function isVendorFile(string $file): bool
    {
        if ($file === '') {
            return false;
        }

        $path = str_replace('\\', '/', $file);

        foreach (self::FOREIGN_PATH_MARKERS as $marker) {
            if (str_contains($path, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function report(
        string $description,
        int $severity,
        string $message,
        string $file,
        int $line,
        array $context,
    ): void {
        try {
            Log::warning(
                'Telegram vendor notice demoted',
                $context + [
                    'operation' => $description,
                    'severity' => $severity,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'frames' => self::frames(),
                ],
            );
        } catch (Throwable) {
            /*
             * The whole point is that the operation survives; a logger
             * that is itself broken must not undo that.
             */
        }
    }

    /**
     * @return list<string>
     */
    private static function frames(): array
    {
        $frames = [];

        $trace = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS,
            self::TRACE_FRAMES + 3,
        );

        foreach (array_slice($trace, 3) as $frame) {
            $frames[] = sprintf(
                '%s%s%s() at %s:%d',
                $frame['class'] ?? '',
                isset($frame['class']) ? ($frame['type'] ?? '::') : '',
                $frame['function'] ?? '{closure}',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0,
            );
        }

        return $frames;
    }
}
