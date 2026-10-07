<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The auto replies file: one JSON, written from the panel or by hand, read
 * on every message - never cached, the listener is a long-running process
 * and a change applies from the next message on.
 *
 * Until the file exists, config/auto_replies.php's defaults are used. A
 * file that cannot be read answers nothing: a hand edit gone wrong must
 * not turn into replies nobody wrote.
 */
final class AutoReplyStore
{
    public function path(): string
    {
        return (string) config('auto_replies.path');
    }

    public function current(): AutoReplyRules
    {
        $data = $this->read();

        return match (true) {
            $data === null => $this->defaults(),
            $data === false => AutoReplyRules::off(),
            default => AutoReplyRules::fromArray($data),
        };
    }

    public function defaults(): AutoReplyRules
    {
        return AutoReplyRules::fromArray((array) config('auto_replies.defaults', []));
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Why the file cannot be used, or null when it can (or is not there).
     */
    public function error(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $raw = @file_get_contents($this->path());

        if ($raw === false) {
            return 'The file cannot be read.';
        }

        json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_last_error_msg();
        }

        return is_array(json_decode($raw, true)) ? null : 'The file does not hold a JSON object.';
    }

    /**
     * Written to a temporary file next to it and moved over, so the
     * listener never reads half a file.
     */
    public function save(AutoReplyRules $rules): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $json = json_encode(
            $rules->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException("Cannot write {$path}");
        }
    }

    /**
     * Back to the config's defaults.
     */
    public function reset(): void
    {
        if ($this->exists()) {
            @unlink($this->path());
        }
    }

    /**
     * What the file holds: null when there is no file, false when it
     * cannot be used.
     *
     * @return array<string, mixed>|false|null
     */
    private function read(): array|false|null
    {
        if (! $this->exists()) {
            return null;
        }

        $raw = @file_get_contents($this->path());
        $data = $raw === false ? null : json_decode($raw, true);

        if (! is_array($data)) {
            Log::error(
                'Auto replies file cannot be used: nothing is answered',
                [
                    'path' => $this->path(),
                    'error' => $raw === false ? 'unreadable' : json_last_error_msg(),
                ],
            );

            return false;
        }

        return $data;
    }
}
