<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Amp\async;
use function Amp\Future\awaitAll;

/**
 * GIFs out of Telegram itself, for the auto replies: what the GIF tab of a
 * Telegram app finds - @gif's search, or the account's saved GIFs when
 * nothing is typed - sent back as that very document, so the person gets a
 * real GIF, not an uploaded video.
 *
 * Only the listener owns the session, so the panel never talks to Telegram:
 * it leaves a job in the cache (ask(), and save on pick()) and the
 * listener's cron does it (serve()), downloading each GIF found next to
 * the auto replies media (telegram/<document id>.mp4) for the panel's
 * preview.
 *
 * A GIF picked is saved to the account's saved GIFs: MadelineProto can then
 * refresh its file reference, which expires, from there (send()).
 */
final class AutoReplyTelegramGifs
{
    public const PAGE = 24;

    /**
     * What @gif is called; Telegram's own GIF search.
     */
    private const BOT = '@gif';

    /**
     * Bigger than this is not shown: a GIF is a few hundred kilobytes.
     */
    private const MAX_BYTES = 4 * 1024 * 1024;

    private const INBOX = 'auto_reply_tg_gifs:inbox';

    /**
     * How long a search's answer is kept: the panel picks from it.
     */
    private const TTL = 3600;

    /**
     * A search older than this is not served: the panel has given up.
     */
    private const STALE_SECONDS = 30;

    /**
     * The previews are kept this long, then removed.
     */
    private const PREVIEW_HOURS = 24;

    /*
    |--------------------------------------------------------------------------
    | The panel
    |--------------------------------------------------------------------------
    */

    /**
     * A search for the listener to run; the id to ask answer() with.
     */
    public function ask(string $query, string $offset): string
    {
        $id = bin2hex(random_bytes(12));

        Cache::put($this->key($id), ['status' => 'pending'], self::TTL);

        $this->push([
            'type' => 'search',
            'id' => $id,
            'query' => mb_substr(trim($query), 0, 60),
            'offset' => mb_substr($offset, 0, 64),
            'at' => time(),
        ]);

        $this->prunePreviews();

        return $id;
    }

    /**
     * pending, failed (with error), or done: items, next_offset (null on the
     * last page). Null for an id nobody asked or one long gone.
     *
     * @return array<string, mixed>|null
     */
    public function answer(string $id): ?array
    {
        $answer = Cache::get($this->key($id));

        return is_array($answer) ? $answer : null;
    }

    /**
     * The GIF $document of search $id, as the rules keep it but for the
     * file, which AutoReplyMedia::copy() makes out of `preview`; queued to
     * be saved to the account's saved GIFs. Null when it is not there.
     *
     * @return array{preview: string, name: string, telegram: array{id: string, access_hash: string, file_reference: string}}|null
     */
    public function pick(string $id, string $document): ?array
    {
        $answer = $this->answer($id);

        foreach ($answer['items'] ?? [] as $item) {
            if ($item['id'] !== $document) {
                continue;
            }

            $preview = $this->previewPath($item['preview']);

            if ($preview === null) {
                return null;
            }

            $telegram = [
                'id' => $item['id'],
                'access_hash' => $item['access_hash'],
                'file_reference' => $item['file_reference'],
            ];

            $this->push(['type' => 'save', 'telegram' => $telegram, 'at' => time()]);

            return [
                'preview' => $preview,
                'name' => $answer['query'] !== '' ? $answer['query'] : (string) __('telegram.auto_replies.media.telegram_saved'),
                'telegram' => $telegram,
            ];
        }

        return null;
    }

    /**
     * A preview on disk, or null when the name is not one of ours or the
     * file is gone.
     */
    public function previewPath(string $file): ?string
    {
        if (preg_match('/^-?\d{1,20}\.(mp4|gif)$/', $file) !== 1) {
            return null;
        }

        $path = $this->previewDirectory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /*
    |--------------------------------------------------------------------------
    | The listener
    |--------------------------------------------------------------------------
    */

    /**
     * The jobs the panel left: run from the listener's cron.
     */
    public function serve(SimpleEventHandler $telegram): void
    {
        foreach ($this->take() as $job) {
            try {
                match ($job['type'] ?? null) {
                    'search' => $this->search($telegram, $job),
                    'save' => $this->save($telegram, $job['telegram']),
                    default => null,
                };
            } catch (Throwable $e) {
                Log::warning('Auto reply Telegram GIFs: job failed', [
                    'type' => $job['type'] ?? null,
                    'query' => $job['query'] ?? null,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                if (($job['type'] ?? null) === 'search') {
                    Cache::put($this->key($job['id']), ['status' => 'failed', 'error' => $e->getMessage()], self::TTL);
                }
            }
        }
    }

    /**
     * The GIF as Telegram has it now: from the saved GIFs, whose file
     * reference is fresh, or as the rules keep it when it is not saved any
     * more (removed on the phone, pushed out by 200 newer ones) - saved
     * again for next time.
     *
     * @param array{id: string, access_hash: string, file_reference: string} $gif
     */
    public function send(SimpleEventHandler $telegram, int|string $peer, array $gif, ?int $replyTo = null): void
    {
        $document = $this->saved($telegram, $gif['id']);

        if ($document === null) {
            $document = $this->input($gif);

            try {
                $this->save($telegram, $gif);
            } catch (Throwable $e) {
                Log::warning('Auto reply Telegram GIF could not be saved again', [
                    'id' => $gif['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $telegram->messages->sendMedia([
            'peer' => $peer,
            'media' => ['_' => 'inputMediaDocument', 'id' => $document],
            'message' => '',
            ...($replyTo !== null ? ['reply_to' => [
                '_' => 'inputReplyToMessage',
                'reply_to_msg_id' => $replyTo,
            ]] : []),
        ]);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function search(SimpleEventHandler $telegram, array $job): void
    {
        if (time() - (int) $job['at'] > self::STALE_SECONDS) {
            return;
        }

        $query = (string) $job['query'];
        $offset = (string) $job['offset'];

        if ($query === '') {
            $saved = $telegram->messages->getSavedGifs(['hash' => 0]);
            $documents = array_values($saved['gifs'] ?? []);
            $start = max(0, (int) $offset);
            $page = array_slice($documents, $start, self::PAGE);
            $next = $start + self::PAGE < count($documents) ? (string) ($start + self::PAGE) : null;
        } else {
            $results = $telegram->messages->getInlineBotResults([
                'bot' => self::BOT,
                'peer' => ['_' => 'inputPeerSelf'],
                'query' => $query,
                'offset' => $offset,
            ]);

            $documents = [];

            foreach ($results['results'] ?? [] as $result) {
                if (($result['document']['_'] ?? null) === 'document') {
                    $documents[] = $result['document'];
                }
            }

            /*
             * More than a page comes at once; the rest is not worth the
             * downloads, the next page is.
             */
            $page = array_slice($documents, 0, self::PAGE);
            $next = ($results['next_offset'] ?? '') !== '' ? (string) $results['next_offset'] : null;
        }

        [$errors, $items] = awaitAll(array_map(
            fn (array $document) => async(fn (): ?array => $this->preview($telegram, $document)),
            $page,
        ));

        if ($errors !== []) {
            Log::warning('Auto reply Telegram GIFs: previews failed', [
                'query' => $query,
                'failed' => count($errors),
                'error' => reset($errors)->getMessage(),
            ]);
        }

        ksort($items);

        Cache::put($this->key($job['id']), [
            'status' => 'done',
            'query' => $query,
            'items' => array_values(array_filter($items)),
            'next_offset' => $next,
        ], self::TTL);
    }

    /**
     * A found GIF downloaded for the panel; null for what is not a GIF or
     * too big.
     *
     * @param array<string, mixed> $document
     * @return array{id: string, access_hash: string, file_reference: string, preview: string, w: int, h: int}|null
     */
    private function preview(SimpleEventHandler $telegram, array $document): ?array
    {
        $extension = match ($document['mime_type'] ?? null) {
            'video/mp4' => 'mp4',
            'image/gif' => 'gif',
            default => null,
        };

        if ($extension === null || (int) ($document['size'] ?? 0) > self::MAX_BYTES) {
            return null;
        }

        $width = 0;
        $height = 0;

        foreach ($document['attributes'] ?? [] as $attribute) {
            if (in_array($attribute['_'] ?? null, ['documentAttributeVideo', 'documentAttributeImageSize'], true)) {
                $width = (int) ($attribute['w'] ?? 0);
                $height = (int) ($attribute['h'] ?? 0);
            }
        }

        $directory = $this->previewDirectory();

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create {$directory}");
        }

        $file = $document['id'] . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $file;

        if (! is_file($path)) {
            $partial = $path . '.part';

            $telegram->downloadToFile($document, $partial);

            rename($partial, $path);
        } else {
            touch($path);
        }

        return [
            'id' => (string) $document['id'],
            'access_hash' => (string) $document['access_hash'],
            'file_reference' => base64_encode((string) $document['file_reference']),
            'preview' => $file,
            'w' => $width,
            'h' => $height,
        ];
    }

    /**
     * @param array{id: string, access_hash: string, file_reference: string} $gif
     */
    private function save(SimpleEventHandler $telegram, array $gif): void
    {
        $telegram->messages->saveGif(['id' => $this->input($gif), 'unsave' => false]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function saved(SimpleEventHandler $telegram, string $id): ?array
    {
        $saved = $telegram->messages->getSavedGifs(['hash' => 0]);

        foreach ($saved['gifs'] ?? [] as $document) {
            if ((string) ($document['id'] ?? '') === $id) {
                return [
                    '_' => 'inputDocument',
                    'id' => $document['id'],
                    'access_hash' => $document['access_hash'],
                    'file_reference' => (string) $document['file_reference'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{id: string, access_hash: string, file_reference: string} $gif
     * @return array<string, mixed>
     */
    private function input(array $gif): array
    {
        return [
            '_' => 'inputDocument',
            'id' => (int) $gif['id'],
            'access_hash' => (int) $gif['access_hash'],
            'file_reference' => (string) base64_decode($gif['file_reference'], true),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The jobs
    |--------------------------------------------------------------------------
    */

    /**
     * @param array<string, mixed> $job
     */
    private function push(array $job): void
    {
        Cache::lock(self::INBOX . ':lock', 5)->block(3, function () use ($job): void {
            $inbox = Cache::get(self::INBOX, []);

            $inbox[] = $job;

            Cache::put(self::INBOX, array_slice($inbox, -50), self::TTL);
        });
    }

    /**
     * Without waiting for the lock: the listener's loop must not stand
     * still, the jobs keep for the next tick.
     *
     * @return list<array<string, mixed>>
     */
    private function take(): array
    {
        if (! Cache::has(self::INBOX)) {
            return [];
        }

        $jobs = Cache::lock(self::INBOX . ':lock', 5)->get(
            static fn (): mixed => Cache::pull(self::INBOX, []),
        );

        return is_array($jobs) ? array_values($jobs) : [];
    }

    private function key(string $id): string
    {
        return 'auto_reply_tg_gifs:' . $id;
    }

    private function previewDirectory(): string
    {
        return config('auto_replies.media_path') . DIRECTORY_SEPARATOR . 'telegram';
    }

    private function prunePreviews(): void
    {
        $directory = $this->previewDirectory();

        if (! is_dir($directory)) {
            return;
        }

        $before = time() - self::PREVIEW_HOURS * 3600;

        foreach (scandir($directory) ?: [] as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($path) && (int) @filemtime($path) < $before) {
                @unlink($path);
            }
        }
    }
}
