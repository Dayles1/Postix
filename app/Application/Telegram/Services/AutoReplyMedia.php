<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use danog\MadelineProto\LocalFile;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The GIFs and voice messages of the auto replies: files uploaded from the
 * panel into one folder (auto_replies.media_path), named by a random id,
 * and referred to by that name from the auto replies file.
 *
 * GIF: .mp4 is what Telegram itself sends as a GIF; a real .gif goes as an
 * animated document. One found in Telegram (AutoReplyTelegramGifs) goes as
 * that document, its file here is only the panel's preview. Voice: .ogg
 * (Opus), what Telegram records - any other format would arrive as a voice
 * message nobody can play.
 */
final class AutoReplyMedia
{
    public const GIF_EXTENSIONS = ['gif', 'mp4'];

    public const VOICE_EXTENSIONS = ['ogg', 'oga', 'opus'];

    public const MAX_KILOBYTES = 10 * 1024;

    /**
     * A file uploaded but never saved into the rules is kept this long -
     * another tab may still be about to save it - then removed.
     */
    private const ORPHAN_HOURS = 24;

    public function __construct(
        private readonly AutoReplyTelegramGifs $telegramGifs,
    ) {
    }

    public function directory(): string
    {
        return (string) config('auto_replies.media_path');
    }

    /**
     * @param list<string> $extensions
     */
    public static function validName(string $file, array $extensions): bool
    {
        if (preg_match('/^[a-f0-9]{24}\.([a-z0-9]+)$/', $file, $m) !== 1) {
            return false;
        }

        return in_array($m[1], $extensions, true);
    }

    /**
     * @return list<string>
     */
    public static function extensions(string $type): array
    {
        return $type === AutoReplyRules::MEDIA_VOICE ? self::VOICE_EXTENSIONS : self::GIF_EXTENSIONS;
    }

    /**
     * @return array{file: string, name: string}
     */
    public function store(UploadedFile $upload, string $type): array
    {
        $extension = mb_strtolower($upload->getClientOriginalExtension());

        if (! in_array($extension, self::extensions($type), true)) {
            throw new RuntimeException("Not a {$type} file: .{$extension}");
        }

        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $file = bin2hex(random_bytes(12)) . '.' . $extension;

        $upload->move($directory, $file);

        return [
            'file' => $file,
            'name' => mb_substr($upload->getClientOriginalName(), 0, 120),
        ];
    }

    /**
     * A GIF found in Telegram, its preview copied in under a name of ours.
     *
     * @return array{file: string, name: string}
     */
    public function copy(string $path, string $name): array
    {
        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $file = bin2hex(random_bytes(12)) . '.' . pathinfo($path, PATHINFO_EXTENSION);

        if (! copy($path, $directory . DIRECTORY_SEPARATOR . $file)) {
            throw new RuntimeException("Cannot copy {$path}");
        }

        return ['file' => $file, 'name' => mb_substr($name, 0, 120)];
    }

    /**
     * The file on disk, or null when the name is not one of ours or the
     * file is gone.
     */
    public function path(string $file): ?string
    {
        if (! self::validName($file, [...self::GIF_EXTENSIONS, ...self::VOICE_EXTENSIONS])) {
            return null;
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * @param array{type: string, file: string, name: string, telegram?: array{id: string, access_hash: string, file_reference: string}} $item
     */
    public function send(SimpleEventHandler $telegram, int|string $peer, array $item, ?int $replyTo = null): void
    {
        /*
         * Telegram's own GIF; should Telegram refuse it, the preview goes
         * as an upload - an answer all the same.
         */
        if ($item['type'] === AutoReplyRules::MEDIA_GIF && isset($item['telegram'])) {
            try {
                $this->telegramGifs->send($telegram, $peer, $item['telegram'], $replyTo);

                return;
            } catch (Throwable $e) {
                Log::warning('Auto reply Telegram GIF refused, sending the file', [
                    'file' => $item['file'],
                    'name' => $item['name'],
                    'id' => $item['telegram']['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $path = $this->path($item['file']);

        if ($path === null) {
            throw new RuntimeException("Auto reply media is missing: {$item['file']}");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $media = match (true) {
            $item['type'] === AutoReplyRules::MEDIA_VOICE => [
                'mime_type' => 'audio/ogg',
                'attributes' => [
                    ['_' => 'documentAttributeAudio', 'voice' => true, 'duration' => 0],
                ],
            ],
            $extension === 'mp4' => $this->animation($path, $item),
            default => [
                'mime_type' => 'image/gif',
                'attributes' => [
                    ['_' => 'documentAttributeAnimated'],
                    ['_' => 'documentAttributeFilename', 'file_name' => 'animation.gif'],
                ],
            ],
        };

        $telegram->messages->sendMedia([
            'peer' => $peer,
            'media' => [
                '_' => 'inputMediaUploadedDocument',
                'file' => new LocalFile($path),
                ...$media,
            ],
            'message' => '',
            ...($replyTo !== null ? ['reply_to' => [
                '_' => 'inputReplyToMessage',
                'reply_to_msg_id' => $replyTo,
            ]] : []),
        ]);
    }

    /**
     * An .mp4 as a GIF: with zero for size and duration Telegram shows it
     * as a plain video, so the real ones are read from the file. A sound
     * track makes it a video all the same - the panel refuses such a file,
     * one stored before that is logged.
     *
     * @param array{type: string, file: string, name: string} $item
     * @return array<string, mixed>
     */
    private function animation(string $path, array $item): array
    {
        $info = Mp4Info::read($path);

        if ($info === null || $info['has_audio']) {
            Log::warning(
                'Auto reply GIF may arrive as a video',
                [
                    'file' => $item['file'],
                    'name' => $item['name'],
                    'readable' => $info !== null,
                    'has_audio' => $info['has_audio'] ?? null,
                ],
            );
        }

        return [
            'mime_type' => 'video/mp4',
            'nosound_video' => true,
            'attributes' => [
                [
                    '_' => 'documentAttributeVideo',
                    'supports_streaming' => true,
                    'duration' => $info['duration'] ?? 0,
                    'w' => $info['width'] ?? 0,
                    'h' => $info['height'] ?? 0,
                ],
                ['_' => 'documentAttributeAnimated'],
                ['_' => 'documentAttributeFilename', 'file_name' => 'animation.mp4'],
            ],
        ];
    }

    /**
     * Whether an uploaded .mp4 can be a GIF: Telegram shows one with a
     * sound track as a video.
     */
    public static function silentVideo(string $path): bool
    {
        $info = Mp4Info::read($path);

        return $info === null || ! $info['has_audio'];
    }

    /**
     * Removes the files the rules no longer use, once they are old enough
     * not to be someone's unsaved upload.
     *
     * @param list<string> $used
     */
    public function prune(array $used): void
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return;
        }

        $before = time() - self::ORPHAN_HOURS * 3600;

        foreach (scandir($directory) ?: [] as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;

            if (
                $this->path($file) !== null
                && ! in_array($file, $used, true)
                && (int) @filemtime($path) < $before
            ) {
                @unlink($path);
            }
        }
    }
}
