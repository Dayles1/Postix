<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use danog\MadelineProto\LocalFile;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * The GIFs and voice messages of the auto replies: files uploaded from the
 * panel into one folder (auto_replies.media_path), named by a random id,
 * and referred to by that name from the auto replies file.
 *
 * GIF: .mp4 is what Telegram itself sends as a GIF; a real .gif goes as an
 * animated document. Voice: .ogg (Opus), what Telegram records - any other
 * format would arrive as a voice message nobody can play.
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
     * @param array{type: string, file: string, name: string} $item
     */
    public function send(SimpleEventHandler $telegram, int|string $peer, array $item, ?int $replyTo = null): void
    {
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
            $extension === 'mp4' => [
                'mime_type' => 'video/mp4',
                'nosound_video' => true,
                'attributes' => [
                    ['_' => 'documentAttributeVideo', 'supports_streaming' => true, 'duration' => 0, 'w' => 0, 'h' => 0],
                    ['_' => 'documentAttributeAnimated'],
                ],
            ],
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
