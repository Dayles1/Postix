<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\NameAffixStripper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameAffixStripperTest extends TestCase
{
    private NameAffixStripper $stripper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripper = new NameAffixStripper;
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function affixProvider(): iterable
    {
        yield 'bek suffix' => ['elyorbek', 'elyor'];
        yield 'jon suffix' => ['ikromjon', 'ikrom'];
        yield 'hon suffix' => ['odilhon', 'odil'];
        yield 'han suffix' => ['adilhan', 'adil'];
        yield 'ali suffix' => ['kodirali', 'kodir'];
        yield 'boy suffix' => ['tursunboy', 'tursun'];
        yield 'abdul prefix' => ['abdulkodir', 'kodir'];
        yield 'abdu prefix' => ['abdukabir', 'kabir'];
        yield 'mirza prefix' => ['mirzabotir', 'botir'];
        yield 'prefix and suffix together' => ['abdulkodirbek', 'kodir'];
    }

    #[DataProvider('affixProvider')]
    public function test_known_affixes_are_stripped_to_the_root(string $token, string $expected): void
    {
        $this->assertSame($expected, $this->stripper->core($token));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function untouchedProvider(): iterable
    {
        // Stripping would leave a fragment, not a root.
        yield 'the affix is the whole name' => ['ali'];
        yield 'bek alone' => ['bek'];
        yield 'jon alone' => ['jon'];
        yield 'abdulla is not abdul + la' => ['abdulla'];

        // No known affix at all.
        yield 'plain given name' => ['sherzod'];
        yield 'plain surname' => ['karimov'];

        // Surname endings are deliberately not affixes.
        yield 'ov surname ending survives' => ['aliev'];
        yield 'patronymic root survives' => ['anvarovich'];
    }

    #[DataProvider('untouchedProvider')]
    public function test_tokens_without_a_strippable_affix_are_returned_unchanged(string $token): void
    {
        $this->assertSame($token, $this->stripper->core($token));
    }

    public function test_at_most_one_prefix_and_one_suffix_are_removed(): void
    {
        // "sher" is a prefix and "bek" a suffix; the second prefix-looking
        // fragment inside the root is left alone.
        $this->assertSame('mirzo', $this->stripper->core('shermirzobek'));
    }

    public function test_has_affix_reports_whether_anything_was_removed(): void
    {
        $this->assertTrue($this->stripper->hasAffix('elyorbek'));
        $this->assertFalse($this->stripper->hasAffix('elyor'));
    }
}
