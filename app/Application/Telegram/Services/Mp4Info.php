<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * What Telegram needs to know of an .mp4 to show it as a GIF, read from the
 * file's own boxes - no ffmpeg on the server:
 *
 *   moov/mvhd                  duration
 *   moov/trak/tkhd             the video track's width and height
 *   moov/trak/mdia/hdlr        "vide" or "soun": a sound track makes
 *                              Telegram show the file as a video
 */
final class Mp4Info
{
    /**
     * Boxes whose children are read; everything else (mdat above all) is
     * skipped over.
     */
    private const CONTAINERS = ['moov', 'trak', 'mdia'];

    /**
     * @return array{width: int, height: int, duration: int, has_audio: bool}|null
     *                                                                             null when this is not an .mp4 that can be read
     */
    public static function read(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $info = ['width' => 0, 'height' => 0, 'duration' => 0, 'has_audio' => false, 'moov' => false];

            self::boxes($handle, 0, (int) filesize($path), $info);

            if (! $info['moov']) {
                return null;
            }

            unset($info['moov']);

            return $info;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $info
     */
    private static function boxes($handle, int $from, int $to, array &$info): void
    {
        $offset = $from;

        while ($offset + 8 <= $to) {
            fseek($handle, $offset);

            $header = fread($handle, 8);

            if ($header === false || strlen($header) < 8) {
                return;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $body = $offset + 8;

            if ($size === 1) {
                $large = fread($handle, 8);

                if ($large === false || strlen($large) < 8) {
                    return;
                }

                $size = unpack('J', $large)[1];
                $body += 8;
            } elseif ($size === 0) {
                $size = $to - $offset;
            }

            if ($size < 8 || $offset + $size > $to) {
                return;
            }

            $end = $offset + $size;

            if ($type === 'moov') {
                $info['moov'] = true;
            }

            if ($type === 'trak') {
                $trak = ['width' => 0, 'height' => 0, 'handler' => ''];

                self::trak($handle, $body, $end, $info, $trak);
            } elseif (in_array($type, self::CONTAINERS, true)) {
                self::boxes($handle, $body, $end, $info);
            } elseif ($type === 'mvhd') {
                $info['duration'] = self::duration($handle, $body);
            }

            $offset = $end;
        }
    }

    /**
     * A track: its size from tkhd, what it is from mdia/hdlr.
     *
     * @param resource $handle
     * @param array<string, mixed> $info
     * @param array{width: int, height: int, handler: string} $trak
     */
    private static function trak($handle, int $from, int $to, array &$info, array &$trak): void
    {
        self::walk($handle, $from, $to, $trak);

        if ($trak['handler'] === 'soun') {
            $info['has_audio'] = true;
        }

        if ($trak['handler'] === 'vide' && $info['width'] === 0) {
            $info['width'] = $trak['width'];
            $info['height'] = $trak['height'];
        }
    }

    /**
     * @param resource $handle
     * @param array{width: int, height: int, handler: string} $trak
     */
    private static function walk($handle, int $from, int $to, array &$trak): void
    {
        $offset = $from;

        while ($offset + 8 <= $to) {
            fseek($handle, $offset);

            $header = fread($handle, 8);

            if ($header === false || strlen($header) < 8) {
                return;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);

            if ($size < 8 || $offset + $size > $to) {
                return;
            }

            $body = $offset + 8;

            if ($type === 'mdia') {
                self::walk($handle, $body, $offset + $size, $trak);
            } elseif ($type === 'tkhd') {
                /*
                 * version 0: 20 bytes of times and ids, version 1: 32;
                 * then 52 bytes up to width and height (16.16 fixed).
                 */
                $version = ord((string) fread($handle, 1));

                fseek($handle, $body + 4 + ($version === 1 ? 32 : 20) + 52);

                $size16 = fread($handle, 8);

                if ($size16 !== false && strlen($size16) === 8) {
                    [, $width, $height] = unpack('N2', $size16);

                    $trak['width'] = $width >> 16;
                    $trak['height'] = $height >> 16;
                }
            } elseif ($type === 'hdlr') {
                fseek($handle, $body + 8);

                $trak['handler'] = (string) fread($handle, 4);
            }

            $offset += $size;
        }
    }

    /**
     * mvhd: whole seconds, at least one.
     *
     * @param resource $handle
     */
    private static function duration($handle, int $body): int
    {
        fseek($handle, $body);

        $version = ord((string) fread($handle, 1));

        fseek($handle, $body + 4 + ($version === 1 ? 16 : 8));

        $raw = fread($handle, $version === 1 ? 12 : 8);

        if ($raw === false || strlen($raw) < ($version === 1 ? 12 : 8)) {
            return 0;
        }

        $timescale = unpack('N', substr($raw, 0, 4))[1];
        $duration = $version === 1
            ? unpack('J', substr($raw, 4, 8))[1]
            : unpack('N', substr($raw, 4, 4))[1];

        return $timescale > 0 ? max(1, (int) ceil($duration / $timescale)) : 0;
    }
}
