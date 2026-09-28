<?php

declare(strict_types=1);

namespace App\Application\Telegram\Exceptions;

use RuntimeException;

/**
 * A session step asked for in the wrong state - a code for an account
 * that is not waiting for one, a logout while a command is still running.
 *
 * The reason is a key under telegram.sessions.state_errors, so each caller
 * decides how to phrase it.
 */
final class TelegramAccountStateException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function translated(): string
    {
        $key = "telegram.sessions.state_errors.{$this->reason}";

        $message = __($key);

        return $message === $key ? $this->reason : $message;
    }
}
