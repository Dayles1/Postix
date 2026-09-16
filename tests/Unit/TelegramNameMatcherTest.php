<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Telegram\Services\TelegramNameMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the probabilistic Telegram name matcher.
 *
 * These are NOT identity-verification tests. They check that the matcher
 * produces a reasonable, explainable "how likely is this the same driver"
 * signal, calibrated against the five reference cases from the task and a
 * handful of additional synthetic cases (typo, transliteration, nickname,
 * unrelated, emoji, multi-token corroboration).
 */
final class TelegramNameMatcherTest extends TestCase
{
    private TelegramNameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new TelegramNameMatcher();
    }

    /**
     * CASE 1 — completely unrelated names must score zero, with no
     * "close spelling" style reason attached.
     */
    public function test_case_1_unrelated_names_score_zero(): void
    {
        $result = $this->matcher->match(
            'NAMANJANOVICH NURULLA MAMADJAN',
            'сарвèназ',
            null,
        );

        $this->assertSame(0.0, $result['score']);
        $this->assertSame('no_match', $result['level']);
        $this->assertFalse($result['matched']);
        $this->assertSame([], $result['matched_parts']);
        $this->assertContains('No match', $result['reasons']);
        $this->assertStringNotContainsStringIgnoringCase('spelling', $result['reason']);
    }

    /**
     * CASE 2 — a short fragment (ALI) contained inside a much longer
     * driver token (KURBONALI) is real but weak evidence: it must land
     * in the WEAK/POSSIBLE range, never STRONG, and never be "matched".
     */
    public function test_case_2_short_partial_token_is_weak_not_strong(): void
    {
        $result = $this->matcher->match(
            'GIYASOV KURBONALI ABDURAHIMOVICH',
            'АЛИ',
            null,
        );

        $this->assertFalse($result['matched']);
        $this->assertLessThan(60.0, $result['score']);
        $this->assertContains($result['level'], ['weak', 'possible']);

        $part = $result['matched_parts'][0] ?? null;
        $this->assertNotNull($part);
        $this->assertSame('KURBONALI', $part['to']);
        $this->assertSame('ALI', $part['from']);
        $this->assertSame('Short partial-name match', $part['reason']);
    }

    /**
     * CASE 3 — IKRAMZHON / IKRAMJON is a well-known Uzbek transliteration
     * difference (zh vs j) and must be treated as a very strong match on
     * its own, without needing a surname to corroborate it.
     */
    public function test_case_3_transliteration_difference_is_very_strong(): void
    {
        $result = $this->matcher->match(
            'SAYFULLAYEV IKRAMZHON',
            'ikramjon 🌙',
            null,
        );

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
        $this->assertSame('IKRAMJON', $result['matched_parts'][0]['from']);
        $this->assertSame('IKRAMZHON', $result['matched_parts'][0]['to']);
    }

    /**
     * CASE 4 — BEKHZOD / BEKZOD is a minor human spelling variation and
     * must score near the top of the range, not be dragged down to
     * "not confirmed" just because only a first name was available.
     */
    public function test_case_4_minor_spelling_variation_is_very_strong(): void
    {
        $result = $this->matcher->match(
            'MATYAKUBOV BEKHZOD ALLABERGAN UGLI',
            'Bekzod',
            null,
        );

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
    }

    /**
     * CASE 5 — a Telegram profile name made entirely of emoji carries no
     * identity information at all; emoji must be ignored, not scored.
     */
    public function test_case_5_emoji_only_name_scores_zero(): void
    {
        $result = $this->matcher->match(
            'JURAMIRZAEV AKMAL ANVAR UGLI',
            '🖤🖤🖤',
            null,
        );

        $this->assertSame(0.0, $result['score']);
        $this->assertFalse($result['matched']);
        $this->assertSame('no_match', $result['level']);
        $this->assertSame([], $result['matched_parts']);
    }

    /* -----------------------------------------------------------------
     | Additional synthetic regression tests
     |----------------------------------------------------------------- */

    public function test_exact_match(): void
    {
        $result = $this->matcher->match('BEKZOD', 'BEKZOD', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
        $this->assertSame('very_strong', $result['level']);
    }

    public function test_cyrillic_to_latin_transliteration(): void
    {
        $result = $this->matcher->match('IKRAMZHON', 'ИКРАМЖОН', null);

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
    }

    /**
     * A driver's full given name used as a shortened Telegram name
     * (ABDUKABIR -> KABIR) is common and should be treated as
     * reasonably strong evidence, but it is a longer/more distinctive
     * fragment than a generic 3-letter name, so it should clearly
     * outscore the KURBONALI -> ALI case.
     */
    public function test_nickname_partial_name_outscores_short_generic_fragment(): void
    {
        $nickname = $this->matcher->match('ABDUKABIR', 'KABIR', null);
        $genericFragment = $this->matcher->match('KURBONALI', 'ALI', null);

        $this->assertGreaterThan($genericFragment['score'], $nickname['score']);
        $this->assertGreaterThanOrEqual(60.0, $nickname['score']);
    }

    public function test_weak_partial_short_fragment(): void
    {
        $result = $this->matcher->match('KURBONALI', 'ALI', null);

        $this->assertFalse($result['matched']);
        $this->assertLessThan(60.0, $result['score']);
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

    /**
     * Two independently matching tokens (first name + surname) should
     * corroborate each other and push the score to the very top,
     * without requiring a special-cased "exact full name" shortcut.
     */
    public function test_multiple_matching_tokens_corroborate(): void
    {
        $result = $this->matcher->match('ALIYEV AKMAL', 'Akmal', 'Aliyev');

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(95.0, $result['score']);
        $this->assertSame('exact_full_name', $result['decision']);
    }

    public function test_missing_driver_name_returns_no_data(): void
    {
        $result = $this->matcher->match(null, 'Akmal', null);

        $this->assertSame('no_data', $result['level']);
        $this->assertFalse($result['matched']);
        $this->assertSame(0.0, $result['score']);
    }

    public function test_missing_telegram_name_and_username_returns_no_data(): void
    {
        $result = $this->matcher->match('ALIYEV AKMAL', null, null, null);

        $this->assertSame('no_data', $result['level']);
        $this->assertFalse($result['matched']);
    }

    /**
     * A username that contains the driver's first name should count as
     * supporting evidence even without a matching first/last name field.
     */
    public function test_username_containing_driver_name_matches(): void
    {
        $result = $this->matcher->match('SAYFULLAYEV IKRAMZHON', null, null, 'ikramjon_2024');

        $this->assertTrue($result['matched']);
        $this->assertGreaterThanOrEqual(90.0, $result['score']);
        $this->assertSame('username', $result['matched_parts'][0]['source']);
    }

    /**
     * A single very strong token match must not be capped/diluted just
     * because no second field is available -- this is the core bug the
     * new algorithm fixes relative to the old field-role based engine.
     */
    public function test_single_strong_token_is_not_diluted_by_missing_second_field(): void
    {
        $result = $this->matcher->match('KORRIBANTS TIGRAN SAMVELOVICH', 'Tigran', null);

        $this->assertTrue($result['matched']);
        $this->assertSame(100.0, $result['score']);
    }

    public function test_patronymic_marker_is_not_treated_as_identity_token(): void
    {
        $result = $this->matcher->match('MATYAKUBOV BEKZOD UGLI', 'Bekzod', null);

        foreach ($result['matched_parts'] as $part) {
            $this->assertNotSame('UGLI', $part['to']);
        }
    }
}
