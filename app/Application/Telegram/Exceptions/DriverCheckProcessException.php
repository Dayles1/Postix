<?php

declare(strict_types=1);

namespace App\Application\Telegram\Exceptions;

use RuntimeException;

/**
 * A watchdog / listener action that cannot be carried out right now.
 *
 * The reason is a key under telegram.watchdog.errors.
 */
final class DriverCheckProcessException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function translated(): string
    {
        $key = "telegram.watchdog.errors.{$this->reason}";

        $message = __($key);

        return $message === $key ? $this->reason : $message;
    }
}
