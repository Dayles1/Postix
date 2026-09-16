<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Telegram\Services\TelegramNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the probabilistic Telegram name matcher.
 *
 * These are NOT identity-verification tests. They check that the matcher
 * produces a reasonable, explainable "how likely is this the same driver"
 * signal across the many real-world ways people write their names on
 * Telegram, while staying resistant to false positives.
 *
 * Grouped by category (exact, typo, transliteration, nickname/partial,
 * username, styled Unicode, combining marks, stretched letters, digits,
 * word order, missing data, false positives, corroborating evidence,
 * fuzz-style generated variants).
 */
final class TelegramNameMatcherTest extends TestCase
{
    private TelegramNameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new TelegramNameMatcher;
    }

    /* =====================================================================
     | The five reference cases from the task specification
     |==================================================================== */

    public function test_case_1_unrelated_names_score_zero(): void
    {
        $result = $this->matcher->match('NAMANJANOVICH NURULLA MAMADJAN', 'сарвèназ', null);

        $this->assertSame(0.0, $result['score']);
        $this->assertSame('no_match', $result['level']);
        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['matched_parts']);
        $this->assertContains('No match', $result['reasons']);
        $this->assertStringNotContainsStringIgnoringCase('spelling', $result['reason']);
    }

    public function test_case_2_short_partial_token_is_weak_not_strong(): void
    {
        $result = $this->matcher->match('GIYASOV KURBONALI ABDURAHIMOVICH', 'АЛИ', null);

        $this->assertFalse($result['matched']);
        $this->assertLessThan(60.0, $result['score']);
        $this->assertContains($result['level'], ['weak', 'possible']);

        $part = $result['matched_parts'][0] ?? null;
        $this->assertNotNull($part);
        $this->assertSame('KURBONALI', $part['to']);
        $this->assertSame('ALI', $part['from']);
        $this->assertSame('Short partial-name match', $part['reason']);
    }

    public function test_case_3_transliteration_difference_is_very_strong(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', 'ikramjon 🌙', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
        $this->assertSame('IKRAMJON', $result['matched_parts'][0]['from']);
        $this->assertSame('IKRAMZHON', $result['matched_parts'][0]['to']);
    }

    public function test_case_4_minor_spelling_variation_is_very_strong(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKHZOD ALLABERGAN UGLI', 'Bekzod', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
    }

    public function test_case_5_emoji_only_name_scores_zero(): void
    {
        $result = $this->matcher->match('JURAMIRZAEV AKMAL ANVAR UGLI', '🖤🖤🖤', null);

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['matched']);
        $this->assertSame('no_match', $result['level']);
        $this->assertSame([], $result['matched_parts']);
    }

    /* =====================================================================
     | Exact match
     |==================================================================== */

    public function test_exact_match(): void
    {
        $result = $this->matcher->match('BEKZOD', 'BEKZOD', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
        $this->assertSame('exact', $result['matched_parts'][0]['kind']);
    }

    public function test_exact_short_name_equality_is_not_penalized_as_generic(): void
    {
        // A literal 1:1 equality is real evidence even for a short/common
        // name -- the commonness penalty only applies to the *partial*
        // (substring) comparison tier, never to genuine equality.
        $result = $this->matcher->match('SODIQOV BEK', 'Bek', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    /* =====================================================================
     | Minor typo / spelling variation
     |==================================================================== */

    public function test_typo_single_character_deletion(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKHZOD', 'Bekzod', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
    }

    public function test_typo_adjacent_letter_transposition(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKARMZHON', 'Ikramzhon', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(85.0, $result['score']);
    }

    public function test_typo_vowel_variation(): void
    {
        $result = $this->matcher->match('ABDUKABIR', 'Abdukaber', null);

        $this->assertGreaterThanOrEqual(75.0, $result['score']);
    }

    /* =====================================================================
     | Transliteration (Cyrillic <-> Latin, alternative Latin spellings)
     |==================================================================== */

    public function test_cyrillic_to_latin_transliteration(): void
    {
        $result = $this->matcher->match('IKRAMZHON', 'ИКРАМЖОН', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
    }

    public function test_cyrillic_title_case_transliteration(): void
    {
        $result = $this->matcher->match('IKRAMZHON', 'Икрамжон', null);

        $this->assertTrue($result['matched']);
    }

    public function test_alternative_latin_transliteration_zh_j(): void
    {
        $result = $this->matcher->match('IKRAMZHON', 'IKRAMJON', null);

        $this->assertTrue($result['matched']);
        $this->assertSame('transliteration_variant', $result['matched_parts'][0]['kind']);
    }

    public function test_uzbek_kh_x_h_variants_are_equivalent(): void
    {
        $viaKh = $this->matcher->match('KHAMIDOV', 'Khamidov', null);
        $viaX = $this->matcher->match('XAMIDOV', 'Hamidov', null);

        $this->assertTrue($viaKh['matched']);
        $this->assertTrue($viaX['matched']);
    }

    public function test_uzbek_q_k_variant(): void
    {
        $result = $this->matcher->match('QODIROV', 'Kodirov', null);

        $this->assertTrue($result['matched']);
    }

    public function test_uzbek_gh_g_variant(): void
    {
        $result = $this->matcher->match('GHOFUROV', 'Gofurov', null);

        $this->assertTrue($result['matched']);
    }

    public function test_uzbek_apostrophe_letter_variant(): void
    {
        $result = $this->matcher->match("O'ZBEKOV", 'Ozbekov', null);

        $this->assertTrue($result['matched']);
    }

    /* =====================================================================
     | Nickname / partial name
     |==================================================================== */

    public function test_nickname_partial_name_outscores_short_generic_fragment(): void
    {
        $nickname = $this->matcher->match('ABDUKABIR', 'KABIR', null);
        $genericFragment = $this->matcher->match('KURBONALI', 'ALI', null);

        $this->assertGreaterThan($genericFragment['score'], $nickname['score']);
        $this->assertGreaterThanOrEqual(60.0, $nickname['score']);
    }

    public function test_prefix_nickname_from_first_name(): void
    {
        $result = $this->matcher->match('ABDURASHIDOV MUHAMMAD', 'Muhammadali', null);

        // "muhammad" is a prefix of "muhammadali" -- a real, but not
        // maximal, partial match.
        $this->assertGreaterThan(0.0, $result['score']);
        $this->assertLessThan(90.0, $result['score']);
    }

    public function test_suffix_nickname_is_weak_and_length_sensitive(): void
    {
        $result = $this->matcher->match('MUHAMMADALIYEV', 'Ali', null);

        $this->assertFalse($result['matched']);
        $this->assertLessThan(60.0, $result['score']);
    }

    /* =====================================================================
     | Short / common name handling
     |==================================================================== */

    public function test_weak_partial_short_fragment(): void
    {
        $result = $this->matcher->match('KURBONALI', 'ALI', null);

        $this->assertFalse($result['matched']);
        $this->assertLessThan(60.0, $result['score']);
    }

    public function test_common_given_name_alone_is_not_strong_evidence_as_a_fragment(): void
    {
        // "ALI" is a generic given name; found only as a fragment of a much
        // longer, unrelated surname it must stay weak.
        $result = $this->matcher->match('DAVLATOV KAMOLIDDIN', 'Ali', null);

        $this->assertSame(0.0, $result['score']);
    }

    /* =====================================================================
     | Username-specific matching
     |==================================================================== */

    public function test_username_containing_driver_name(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikramjon_777');

        $this->assertTrue($result['matched']);
        $this->assertSame('username', $result['matched_parts'][0]['source']);
    }

    public function test_username_with_dot_separator(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikram.jon');

        $this->assertGreaterThanOrEqual(85.0, $result['score']);
    }

    public function test_username_with_underscore_separator(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikram_jon');

        $this->assertGreaterThanOrEqual(85.0, $result['score']);
    }

    public function test_username_with_dash_separator(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikram-jon');

        $this->assertGreaterThanOrEqual(85.0, $result['score']);
    }

    public function test_username_with_trailing_digits_agglutinated(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKZOD', null, null, 'bekzod1999');

        $this->assertTrue($result['matched']);
    }

    public function test_username_with_leading_digits_agglutinated(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKZOD', null, null, '007bekzod');

        $this->assertTrue($result['matched']);
    }

    public function test_generic_username_without_any_name_scores_zero(): void
    {
        $result = $this->matcher->match('JURAMIRZAEV AKMAL', null, null, 'best_driver_2025');

        $this->assertSame(0.0, $result['score']);
    }

    /* =====================================================================
     | Styled Unicode ("fancy fonts")
     |==================================================================== */

    public function test_emoji_wrapped_name_still_matches(): void
    {
        $result = $this->matcher->match('ABDUKABIR', '❤️ KABIR ❤️', null);

        $this->assertTrue($result['matched']);
    }

    public function test_mathematical_bold_styled_name_matches(): void
    {
        $result = $this->matcher->match('IKRAMZHON', '𝑰𝑲𝑹𝑨𝑴𝑱𝑶𝑵', null);

        $this->assertTrue($result['matched']);
    }

    public function test_small_caps_styled_name_matches(): void
    {
        $result = $this->matcher->match('KABIR', 'ᴋᴀʙɪʀ', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    /* =====================================================================
     | Combining marks / accents
     |==================================================================== */

    public function test_accented_latin_name_matches_plain_spelling(): void
    {
        $result = $this->matcher->match('JOSE', 'José', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    /* =====================================================================
     | Stretched / repeated letters
     |==================================================================== */

    public function test_stretched_vowels_still_match(): void
    {
        $result = $this->matcher->match('IKRAMZHON', 'IKRAAAMJOOON', null);

        $this->assertTrue($result['matched']);
    }

    public function test_stretched_consonant_still_matches(): void
    {
        $result = $this->matcher->match('ABDUKABIR', 'KABIIIR', null);

        $this->assertGreaterThan(0.0, $result['score']);
    }

    /* =====================================================================
     | Word order independence
     |==================================================================== */

    public function test_reordered_two_field_name_is_exact_full_name(): void
    {
        $result = $this->matcher->match('ALIYEV AKMAL', 'Akmal', 'Aliyev');

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
        $this->assertSame('exact_full_name', $result['decision']);
    }

    public function test_reordered_single_field_name_matches(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', 'Ikramjon Sayfullayev', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    /* =====================================================================
     | Missing data
     |==================================================================== */

    public function test_missing_driver_name_returns_no_data(): void
    {
        $result = $this->matcher->match(null, 'Akmal', null);

        $this->assertSame('no_data', $result['level']);
        $this->assertFalse($result['matched']);
        $this->assertSame(0.0, $result['score']);
    }

    public function test_missing_telegram_last_name_still_matches_on_first_name(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', 'Ikramjon', null);

        $this->assertTrue($result['matched']);
    }

    public function test_missing_telegram_first_name_still_matches_on_last_name(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, 'Sayfullayev');

        $this->assertTrue($result['matched']);
    }

    public function test_only_username_available_still_matches(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikramzhon');

        $this->assertTrue($result['matched']);
    }

    public function test_only_emoji_available_scores_zero(): void
    {
        $result = $this->matcher->match('AKMAL', null, null, '🖤🖤🖤');

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['matched']);
    }

    public function test_missing_telegram_name_and_username_returns_no_data(): void
    {
        $result = $this->matcher->match('ALIYEV AKMAL', null, null, null);

        $this->assertSame('no_data', $result['level']);
        $this->assertFalse($result['matched']);
    }

    /* =====================================================================
     | Multiple / independent evidence sources
     |==================================================================== */

    public function test_multiple_matching_tokens_corroborate(): void
    {
        $result = $this->matcher->match('ALIYEV AKMAL', 'Akmal', 'Aliyev');

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(95.0, $result['score']);
        $this->assertSame('exact_full_name', $result['decision']);
    }

    public function test_first_name_and_username_agreeing_does_not_produce_runaway_score(): void
    {
        // Two sources naming the *same* single driver token must not
        // multiply confidence beyond what the fast-path/normal ceiling
        // already allows.
        $result = $this->matcher->match('BEKZOD', 'Bekzod', null, 'bekzod_1999');

        $this->assertSame(100.0, $result['score']);
    }

    public function test_second_independent_field_corroborates_a_weaker_first_match(): void
    {
        $withoutSecondField = $this->matcher->match('KURBONALI ANVAROVICH', 'Ali', null);
        $withSecondField = $this->matcher->match('KURBONALI ANVAROVICH', 'Ali', 'Anvarovich');

        $this->assertGreaterThan($withoutSecondField['score'], $withSecondField['score']);
    }

    /* =====================================================================
     | Single strong token must not be diluted by a missing second field
     | (the core bug this rewrite fixes)
     |==================================================================== */

    public function test_single_strong_token_is_not_diluted_by_missing_second_field(): void
    {
        $result = $this->matcher->match('KORRIBANTS TIGRAN SAMVELOVICH', 'Tigran', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    /* =====================================================================
     | Patronymic / structural markers are not identity tokens
     |==================================================================== */

    public function test_patronymic_marker_is_not_treated_as_identity_token(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKZOD UGLI', 'Bekzod', null);

        $this->assertNotEmpty($result['matched_parts']);

        foreach ($result['matched_parts'] as $part) {
            $this->assertNotSame('UGLI', $part['to']);
        }
    }

    public function test_ovich_patronymic_suffix_does_not_prevent_root_matching(): void
    {
        $result = $this->matcher->match('GIYASOV ABDURAHIMOVICH', 'Abdurahim', null);

        $this->assertGreaterThan(0.0, $result['score']);
    }

    /* =====================================================================
     | False positives -- adversarial cases
     |==================================================================== */

    public function test_shared_surname_suffix_does_not_cause_false_positive(): void
    {
        // "-OV"/"-EV" is an almost universal Russian/Uzbek surname
        // ending; two otherwise unrelated surnames must not match just
        // because they share it.
        $result = $this->matcher->match('ALIYEV AKMAL', 'Karimov', null);

        $this->assertSame(0.0, $result['score']);
    }

    public function test_unrelated_names_score_zero(): void
    {
        $result = $this->matcher->match('NURULLA', 'SARVENAZ', null);

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['matched']);
    }

    public function test_emoji_only_scores_zero(): void
    {
        $result = $this->matcher->match('AKMAL', null, null, '🖤🖤🖤');

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['matched']);
    }

    public function test_long_unrelated_names_do_not_get_meaningful_noise_score(): void
    {
        $result = $this->matcher->match(
            'ABDURAKHMANOV SHUKHRAT TASHKENBAYEVICH',
            'Christopher Montgomery',
            null,
        );

        $this->assertSame(0.0, $result['score']);
    }

    public function test_two_short_names_sharing_one_letter_do_not_match(): void
    {
        $result = $this->matcher->match('MAX', 'SAM', null);

        $this->assertSame(0.0, $result['score']);
    }

    /* =====================================================================
     | Fuzz-style generated variants
     |==================================================================== */

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function ikramzhonVariantProvider(): iterable
    {
        yield 'alternative transliteration' => ['IKRAMJON'];
        yield 'lowercase' => ['ikramjon'];
        yield 'cyrillic' => ['Икрамжон'];
        yield 'styled mathematical italic' => ['𝑰𝑲𝑹𝑨𝑴𝑱𝑶𝑵'];
        yield 'stretched vowels' => ['IKRAAAMJON'];
        yield 'username with digits' => ['ikramjon_777'];
        yield 'transposed letters' => ['IKARMJON'];
    }

    #[DataProvider('ikramzhonVariantProvider')]
    public function test_ikramzhon_variants_all_score_reasonably_high(string $variant): void
    {
        $result = str_contains($variant, '_')
            ? $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, $variant)
            : $this->matcher->match('SAYFULLAYEV IKRAMZHON', $variant, null);

        $this->assertGreaterThanOrEqual(75.0, $result['score'], "Variant '{$variant}' scored too low: {$result['score']}");
    }

    public function test_fuzz_variants_do_not_make_an_unrelated_control_name_spike(): void
    {
        $control = $this->matcher->match('NAMANJANOVICH NURULLA MAMADJAN', 'IKRAMJON', null);

        $this->assertSame(0.0, $control['score']);
    }
}
