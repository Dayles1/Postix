<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\NameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameNormalizerTest extends TestCase
{
    private NameNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new NameNormalizer;
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function styledUnicodeProvider(): iterable
    {
        yield 'mathematical bold' => ['𝐊𝐀𝐁𝐈𝐑'];
        yield 'mathematical bold italic' => ['𝑲𝑨𝑩𝑰𝑹'];
        yield 'mathematical script' => ['𝓚𝓐𝓑𝓘𝓡'];
        yield 'mathematical double-struck' => ['𝕂𝔸𝔹𝕀ℝ'];
        yield 'mathematical monospace' => ['𝙺𝙰𝙱𝙸𝚁'];
        yield 'fullwidth' => ['ＫＡＢＩＲ'];
        yield 'circled' => ['ⓀⒶⒷⒾⓇ'];
        yield 'small caps' => ['ᴋᴀʙɪʀ'];
        yield 'superscript' => ['ᵏᵃᵇᶦʳ'];
    }

    #[DataProvider('styledUnicodeProvider')]
    public function test_styled_unicode_decodes_to_plain_letters(string $styled): void
    {
        $this->assertSame('kabir', $this->normalizer->normalize($styled));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function combiningMarkProvider(): iterable
    {
        yield 'precomposed e-acute' => ['José'];
        yield 'decomposed e-acute (e + combining acute)' => ["Jose\u{0301}"];
        yield 'uppercase' => ['JOSE'];
        yield 'accented uppercase' => ['JÓSE'];
        yield 'grave accent' => ['Josè'];
    }

    #[DataProvider('combiningMarkProvider')]
    public function test_combining_marks_and_case_normalize_identically(string $variant): void
    {
        $this->assertSame('jose', $this->normalizer->normalize($variant));
    }

    public function test_emoji_and_decorative_symbols_are_removed(): void
    {
        $this->assertSame('kabir', $this->normalizer->normalize('❤️ KABIR ❤️'));
        $this->assertSame('kabir', $this->normalizer->normalize('🖤KABIR🖤'));
        $this->assertSame('', $this->normalizer->normalize('🖤🖤🖤'));
        $this->assertSame('', $this->normalizer->normalize('❤️❤️❤️'));
    }

    public function test_zero_width_and_variation_selector_characters_are_stripped(): void
    {
        $this->assertSame('kabir', $this->normalizer->normalize("KA\u{200B}BIR"));
        $this->assertSame('kabir', $this->normalizer->normalize("KABIR\u{FE0F}"));
    }

    public function test_decorative_separators_become_whitespace(): void
    {
        $this->assertSame('kabir', $this->normalizer->normalize('KABIR.'));
        $this->assertSame('kabir', $this->normalizer->normalize('KABIR_'));
        $this->assertSame('kabir', $this->normalizer->normalize('KABIR-'));
        $this->assertSame('kabir uz', $this->normalizer->normalize('KABIR|UZ'));
        $this->assertSame('kabir uz', $this->normalizer->normalize('KABIR•UZ'));
        $this->assertSame('kabir', $this->normalizer->normalize('KABIR⚡'));
    }

    public function test_cyrillic_transliterates_to_latin(): void
    {
        $this->assertSame('ikramzhon', $this->normalizer->normalize('ИКРАМЖОН'));
        $this->assertSame('ikramzhon', $this->normalizer->normalize('Икрамжон'));
    }

    public function test_uzbek_cyrillic_extensions_transliterate(): void
    {
        $this->assertSame('gofur', $this->normalizer->normalize('Ғофур'));
        $this->assertSame('hasan', $this->normalizer->normalize('Ҳасан'));
        $this->assertSame('ulugbek', $this->normalizer->normalize('Улуғбек'));
    }

    public function test_apostrophe_variants_are_stripped_consistently(): void
    {
        $this->assertSame(
            $this->normalizer->normalize("O'ZBEK"),
            $this->normalizer->normalize('Oʻzbek'),
        );
    }

    public function test_stretched_letters_collapse_but_doubled_letters_survive(): void
    {
        $this->assertSame('kabir', $this->normalizer->normalize('KABIIIR'));
        $this->assertSame('ikramjon', $this->normalizer->normalize('IKRAMJJJON'));

        // Genuine doubled letters in real names must not be destroyed here.
        $this->assertSame('anna', $this->normalizer->normalize('ANNA'));
        $this->assertSame('alla', $this->normalizer->normalize('ALLA'));
    }

    public function test_username_normalization_removes_all_separators(): void
    {
        $this->assertSame('ikramjon', $this->normalizer->normalizeUsername('@ikram.jon'));
        $this->assertSame('ikramjon', $this->normalizer->normalizeUsername('@ikram_jon'));
        $this->assertSame('ikramjon', $this->normalizer->normalizeUsername('@ikram-jon'));
    }
}
