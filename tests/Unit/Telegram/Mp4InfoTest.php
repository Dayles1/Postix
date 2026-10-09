<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\Telegram\Services\Mp4Info;
use PHPUnit\Framework\TestCase;

final class Mp4InfoTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * The boxes Mp4Info reads, the media data first - as many encoders
     * write it - so moov has to be found past it.
     */
    public static function mp4(int $width, int $height, int $timescale, int $duration, bool $audio = false): string
    {
        $box = static fn (string $type, string $body): string => pack('N', 8 + strlen($body)) . $type . $body;

        $trak = static fn (string $handler, int $w, int $h): string => $box(
            'trak',
            $box('tkhd', str_repeat("\0", 4 + 20 + 52) . pack('N2', $w << 16, $h << 16))
            . $box('mdia', $box('hdlr', str_repeat("\0", 8) . $handler . str_repeat("\0", 13))),
        );

        return $box('ftyp', 'isom' . pack('N', 512) . 'isomiso2avc1mp41')
            . $box('mdat', str_repeat("\x55", 1000))
            . $box(
                'moov',
                $box('mvhd', str_repeat("\0", 12) . pack('N2', $timescale, $duration) . str_repeat("\0", 80))
                . $trak('vide', $width, $height)
                . ($audio ? $trak('soun', 0, 0) : ''),
            );
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    public function test_it_reads_the_size_and_duration_of_a_silent_video(): void
    {
        $this->assertSame(
            ['width' => 480, 'height' => 270, 'duration' => 3, 'has_audio' => false],
            Mp4Info::read($this->file(self::mp4(480, 270, 1000, 2400))),
        );
    }

    public function test_it_sees_a_sound_track(): void
    {
        $info = Mp4Info::read($this->file(self::mp4(320, 320, 600, 600, audio: true)));

        $this->assertTrue($info['has_audio']);
        $this->assertSame(320, $info['width']);
        $this->assertSame(1, $info['duration']);
    }

    public function test_anything_else_is_not_read(): void
    {
        $this->assertNull(Mp4Info::read($this->file('GIF89a not a video')));
        $this->assertNull(Mp4Info::read($this->file('')));
        $this->assertNull(Mp4Info::read(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '.mp4'));
    }
}
