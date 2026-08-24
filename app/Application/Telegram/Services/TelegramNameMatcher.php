<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

final class TelegramNameMatcher
{
    /*
     * ============================================================
     * SCORE CONFIGURATION
     * ============================================================
     */

    private const CONFIRM_SCORE = 80.0;

    private const STRONG_TOKEN_SCORE = 82.0;

    private const USEFUL_TOKEN_SCORE = 55.0;

    private const MIN_TOKEN_LENGTH = 2;

    /**
     * Some very short names produce too many false positives.
     */
    private const SINGLE_TOKEN_CONFIRM_LENGTH = 7;

    /**
     * Maximum number of token results kept in response.
     */
    private const MAX_MATCHES = 100;

    /*
     * ============================================================
     * PUBLIC API
     * ============================================================
     */

    public function match(
        ?string $expectedName,
        ?string $telegramFirstName,
        ?string $telegramLastName,
    ): array {
        $telegramFullName = trim(
            implode(
                ' ',
                array_filter(
                    [
                        $telegramFirstName,
                        $telegramLastName,
                    ],
                    static fn($value): bool =>
                    $value !== null
                    && trim((string) $value) !== '',
                )
            )
        );

        if (
            $this->isBlank($expectedName)
            || $this->isBlank($telegramFullName)
        ) {
            return $this->noDataResult(
                $expectedName,
                $telegramFullName ?: null,
            );
        }

        /*
         * --------------------------------------------------------
         * Normalize names.
         * --------------------------------------------------------
         */
        $normalizedExpected = $this->normalizeName(
            $expectedName
        );

        $normalizedTelegram = $this->normalizeName(
            $telegramFullName
        );

        if (
            $normalizedExpected === ''
            || $normalizedTelegram === ''
        ) {
            return $this->noDataResult(
                $expectedName,
                $telegramFullName,
            );
        }

        /*
         * --------------------------------------------------------
         * Tokenize.
         * --------------------------------------------------------
         */
        $expectedTokens = $this->tokens(
            $expectedName
        );

        $telegramTokens = $this->tokens(
            $telegramFullName
        );

        if (
            $expectedTokens === []
            || $telegramTokens === []
        ) {
            return $this->noDataResult(
                $expectedName,
                $telegramFullName,
            );
        }

        /*
         * --------------------------------------------------------
         * Exact full-name equality.
         * --------------------------------------------------------
         */
        if (
            $normalizedExpected === $normalizedTelegram
        ) {
            $matches = [];

            foreach ($expectedTokens as $token) {
                $matches[] = [
                    'expected' => $token,
                    'actual' => $token,
                    'score' => 100.0,
                    'reason' => 'exact',
                ];
            }

            return $this->result(
                expectedName: $expectedName,
                telegramName: $telegramFullName,
                normalizedExpected: $normalizedExpected,
                normalizedTelegram: $normalizedTelegram,
                matched: true,
                score: 100.0,
                level: 'exact',
                matches: $matches,
                expectedTokens: $expectedTokens,
                telegramTokens: $telegramTokens,
                reasons: [
                    'exact_full_name',
                ],
            );
        }

        /*
         * --------------------------------------------------------
         * Token matching.
         * --------------------------------------------------------
         */
        $matches = $this->buildTokenMatches(
            $expectedTokens,
            $telegramTokens,
        );

        if ($matches === []) {
            return $this->result(
                expectedName: $expectedName,
                telegramName: $telegramFullName,
                normalizedExpected: $normalizedExpected,
                normalizedTelegram: $normalizedTelegram,
                matched: false,
                score: 0.0,
                level: 'none',
                matches: [],
                expectedTokens: $expectedTokens,
                telegramTokens: $telegramTokens,
                reasons: [
                    'no_token_match',
                ],
            );
        }

        $strongMatches = array_values(
            array_filter(
                $matches,
                static fn(array $match): bool =>
                (float) $match['score']
                >= self::STRONG_TOKEN_SCORE
            )
        );

        $usefulMatches = array_values(
            array_filter(
                $matches,
                static fn(array $match): bool =>
                (float) $match['score']
                >= self::USEFUL_TOKEN_SCORE
            )
        );

        /*
         * --------------------------------------------------------
         * Full-name similarity.
         * --------------------------------------------------------
         */
        $fullNameSimilarity =
            $this->stringSimilarity(
                $normalizedExpected,
                $normalizedTelegram,
            );

        /*
         * --------------------------------------------------------
         * Weighted token similarity.
         * --------------------------------------------------------
         */
        $weightedTokenScore =
            $this->weightedTokenScore(
                $matches
            );

        /*
         * --------------------------------------------------------
         * Coverage.
         * --------------------------------------------------------
         */
        $expectedCount = count(
            $expectedTokens
        );

        $strongCount = count(
            $strongMatches
        );

        $coverage =
            $expectedCount > 0
            ? $strongCount / $expectedCount
            : 0.0;

        $coverageScore =
            $coverage * 100.0;

        /*
         * --------------------------------------------------------
         * Partial-name evidence.
         * --------------------------------------------------------
         */
        $partialScore =
            $this->partialNameScore(
                $expectedTokens,
                $telegramTokens,
                $strongMatches,
            );

        /*
         * --------------------------------------------------------
         * Multi-token evidence.
         * --------------------------------------------------------
         */
        $multiTokenScore =
            $this->multiTokenScore(
                $strongMatches
            );

        /*
         * --------------------------------------------------------
         * Identity-token evidence.
         *
         * Long exact tokens are much more informative
         * than "ali", "bek", etc.
         * --------------------------------------------------------
         */
        $identityScore =
            $this->identityTokenScore(
                $strongMatches
            );

        /*
         * --------------------------------------------------------
         * Initial final score.
         * --------------------------------------------------------
         */
        $score =
            ($weightedTokenScore * 0.45)
            + ($fullNameSimilarity * 0.20)
            + ($coverageScore * 0.15)
            + ($partialScore * 0.10)
            + ($multiTokenScore * 0.05)
            + ($identityScore * 0.05);

        /*
         * --------------------------------------------------------
         * Strong single-token rescue.
         *
         * Example:
         *
         * AKHMADJONOV ILYOSBEK KHUSANBOY UGLI
         *                ↓
         *             ILYOSBEK
         *
         * This is NOT "no data".
         * It is strong partial evidence.
         * --------------------------------------------------------
         */
        $bestMatch = $strongMatches[0] ?? null;

        if ($bestMatch) {
            $bestExpected = (string) (
                $bestMatch['expected'] ?? ''
            );

            $bestTokenScore = (float) (
                $bestMatch['score'] ?? 0
            );

            if (
                mb_strlen($bestExpected) >=
                self::SINGLE_TOKEN_CONFIRM_LENGTH
                && $bestTokenScore >= 98
            ) {
                $score = max(
                    $score,
                    82.0
                );
            }
        }

        $score = round(
            min(
                100.0,
                max(
                    0.0,
                    $score
                )
            ),
            2
        );

        /*
         * --------------------------------------------------------
         * Confidence level.
         * --------------------------------------------------------
         */
        $level = $this->resolveLevel(
            $score,
            $strongMatches,
            $expectedTokens,
            $telegramTokens,
        );

        /*
         * --------------------------------------------------------
         * Final boolean.
         * --------------------------------------------------------
         */
        $matched = $this->isConfirmed(
            $score,
            $strongMatches,
        );

        /*
         * --------------------------------------------------------
         * Explanation.
         * --------------------------------------------------------
         */
        $reasons = $this->buildReasons(
            $expectedTokens,
            $telegramTokens,
            $matches,
            $score,
            $matched,
        );

        return $this->result(
            expectedName: $expectedName,
            telegramName: $telegramFullName,
            normalizedExpected: $normalizedExpected,
            normalizedTelegram: $normalizedTelegram,
            matched: $matched,
            score: $score,
            level: $level,
            matches: $matches,
            expectedTokens: $expectedTokens,
            telegramTokens: $telegramTokens,
            reasons: $reasons,
            weightedTokenScore: $weightedTokenScore,
            fullNameSimilarity: $fullNameSimilarity,
            partialScore: $partialScore,
            multiTokenScore: $multiTokenScore,
            identityScore: $identityScore,
            matchedTokenCount: count($strongMatches),
            usefulTokenCount: count($usefulMatches),
        );
    }

    /*
     * ============================================================
     * TOKEN MATCHING
     * ============================================================
     */

    private function buildTokenMatches(
        array $expectedTokens,
        array $telegramTokens,
    ): array {
        $candidates = [];

        foreach ($expectedTokens as $expectedIndex => $expected) {
            foreach ($telegramTokens as $telegramIndex => $actual) {
                $comparison =
                    $this->tokenSimilarity(
                        $expected,
                        $actual,
                    );

                $candidates[] = [
                    'expected' => $expected,
                    'actual' => $actual,
                    'score' => $comparison['score'],
                    'reason' => $comparison['reason'],
                    'expected_index' => $expectedIndex,
                    'actual_index' => $telegramIndex,
                ];
            }
        }

        usort(
            $candidates,
            static fn(
            array $a,
            array $b
        ): int =>
            $b['score'] <=> $a['score']
        );

        /*
         * One Telegram token may only be consumed once.
         */
        $usedExpected = [];
        $usedActual = [];

        $matches = [];

        foreach ($candidates as $candidate) {
            $expectedIndex =
                $candidate['expected_index'];

            $actualIndex =
                $candidate['actual_index'];

            if (
                isset($usedExpected[$expectedIndex])
                || isset($usedActual[$actualIndex])
            ) {
                continue;
            }

            if (
                $candidate['score']
                < self::USEFUL_TOKEN_SCORE
            ) {
                continue;
            }

            $usedExpected[$expectedIndex] = true;
            $usedActual[$actualIndex] = true;

            unset(
                $candidate['expected_index'],
                $candidate['actual_index'],
            );

            $matches[] = $candidate;
        }

        usort(
            $matches,
            static fn(
            array $a,
            array $b
        ): int =>
            $b['score'] <=> $a['score']
        );

        return array_slice(
            $matches,
            0,
            self::MAX_MATCHES,
        );
    }

    private function tokenSimilarity(
        string $expected,
        string $actual,
    ): array {
        if ($expected === $actual) {
            return [
                'score' => 100.0,
                'reason' => 'exact',
            ];
        }

        /*
         * Canonical comparison.
         */
        $expectedCompact =
            $this->compactToken($expected);

        $actualCompact =
            $this->compactToken($actual);

        if (
            $expectedCompact !== ''
            && $expectedCompact === $actualCompact
        ) {
            return [
                'score' => 99.5,
                'reason' => 'canonical_equal',
            ];
        }

        /*
         * Phonetic/orthographic form.
         */
        $expectedPhonetic =
            $this->phoneticForm($expected);

        $actualPhonetic =
            $this->phoneticForm($actual);

        if (
            $expectedPhonetic !== ''
            && $expectedPhonetic === $actualPhonetic
        ) {
            return [
                'score' => 97.0,
                'reason' => 'phonetic_equal',
            ];
        }

        /*
         * Contains.
         */
        if (
            mb_strlen($expected) >= 4
            && mb_strlen($actual) >= 4
            && (
                mb_strpos(
                    $expected,
                    $actual
                ) !== false
                || mb_strpos(
                    $actual,
                    $expected
                ) !== false
            )
        ) {
            return [
                'score' => 95.0,
                'reason' => 'contains',
            ];
        }

        /*
         * Fuzzy.
         */
        return [
            'score' =>
                $this->stringSimilarity(
                    $expected,
                    $actual,
                ),
            'reason' => 'fuzzy',
        ];
    }

    /*
     * ============================================================
     * SCORING
     * ============================================================
     */

    private function weightedTokenScore(
        array $matches,
    ): float {
        if ($matches === []) {
            return 0.0;
        }

        $weightTotal = 0.0;
        $scoreTotal = 0.0;

        foreach ($matches as $match) {
            $token = (string) (
                $match['expected'] ?? ''
            );

            $length = mb_strlen(
                $token
            );

            $weight = match (true) {
                $length >= 10 => 1.30,
                $length >= 8 => 1.20,
                $length >= 6 => 1.10,
                $length >= 4 => 1.00,
                default => 0.70,
            };

            $weightTotal += $weight;

            $scoreTotal +=
                ((float) $match['score'])
                * $weight;
        }

        return $weightTotal > 0
            ? round(
                $scoreTotal / $weightTotal,
                2
            )
            : 0.0;
    }

    private function partialNameScore(
        array $expectedTokens,
        array $telegramTokens,
        array $strongMatches,
    ): float {
        if (
            $expectedTokens === []
            || $telegramTokens === []
            || $strongMatches === []
        ) {
            return 0.0;
        }

        $expectedCount =
            count($expectedTokens);

        $telegramCount =
            count($telegramTokens);

        $strongCount =
            count($strongMatches);

        /*
         * Telegram contains only one strong token.
         */
        if (
            $telegramCount === 1
            && $strongCount >= 1
        ) {
            $token = (string) (
                $strongMatches[0]['expected']
                ?? ''
            );

            if (
                mb_strlen($token) >= 8
                && $strongMatches[0]['score'] >= 98
            ) {
                return 100.0;
            }

            if (
                mb_strlen($token) >= 6
                && $strongMatches[0]['score'] >= 95
            ) {
                return 90.0;
            }

            return 70.0;
        }

        /*
         * Telegram has fewer tokens than expected.
         */
        if (
            $telegramCount < $expectedCount
            && $strongCount > 0
        ) {
            return 85.0;
        }

        /*
         * Telegram has extra tokens.
         */
        if (
            $telegramCount > $expectedCount
            && $strongCount > 0
        ) {
            return 75.0;
        }

        return 0.0;
    }

    private function multiTokenScore(
        array $strongMatches,
    ): float {
        return match (count($strongMatches)) {
            0 => 0.0,
            1 => 30.0,
            2 => 75.0,
            3 => 90.0,
            default => 100.0,
        };
    }

    private function identityTokenScore(
        array $strongMatches,
    ): float {
        $best = 0.0;

        foreach ($strongMatches as $match) {
            $token = (string) (
                $match['expected'] ?? ''
            );

            $length = mb_strlen(
                $token
            );

            $score = (float) (
                $match['score'] ?? 0
            );

            if (
                $length >= 10
                && $score >= 95
            ) {
                $best = max(
                    $best,
                    100.0
                );

                continue;
            }

            if (
                $length >= 8
                && $score >= 95
            ) {
                $best = max(
                    $best,
                    90.0
                );

                continue;
            }

            if (
                $length >= 6
                && $score >= 95
            ) {
                $best = max(
                    $best,
                    80.0
                );
            }
        }

        return $best;
    }

    private function resolveLevel(
        float $score,
        array $strongMatches,
        array $expectedTokens,
        array $telegramTokens,
    ): string {
        if ($score >= 95) {
            return 'exact';
        }

        if (
            $score >= 88
            && $strongMatches !== []
        ) {
            return 'very_high';
        }

        if (
            $score >= 80
            && $strongMatches !== []
        ) {
            return 'high';
        }

        if (
            $score >= 70
            && $strongMatches !== []
        ) {
            return 'medium';
        }

        if (
            $score >= 55
            && $strongMatches !== []
        ) {
            return 'low';
        }

        if (
            $expectedTokens !== []
            && $telegramTokens !== []
        ) {
            return 'very_low';
        }

        return 'no_data';
    }

    private function isConfirmed(
        float $score,
        array $strongMatches,
    ): bool {
        if ($score >= self::CONFIRM_SCORE) {
            return true;
        }

        /*
         * A single long exact identity token can confirm
         * a partial Telegram name.
         */
        foreach ($strongMatches as $match) {
            $expected = (string) (
                $match['expected'] ?? ''
            );

            $matchScore = (float) (
                $match['score'] ?? 0
            );

            if (
                mb_strlen($expected)
                >= self::SINGLE_TOKEN_CONFIRM_LENGTH
                && $matchScore >= 98
            ) {
                return true;
            }
        }

        return false;
    }

    /*
     * ============================================================
     * NORMALIZATION
     * ============================================================
     */

    private function normalizeName(
        string $name
    ): string {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        /*
         * NFC/NFKC where intl is available.
         */
        if (
            class_exists(\Normalizer::class)
        ) {
            $normalized =
                \Normalizer::normalize(
                    $name,
                    \Normalizer::FORM_KC
                );

            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        /*
         * Most important stage:
         *
         * 𝙄𝙇𝙔𝙊...
         * 🅸🅻🆈...
         * ＩＬＹ...
         * ⒾⓁⓎ...
         *
         * become normal ASCII letters.
         */
        $name =
            $this->decodeStyledCharacters(
                $name
            );

        /*
         * Remove zero-width / variation selectors.
         */
        $name = preg_replace(
            '/['
            . '\x{200B}-\x{200D}'
            . '\x{2060}'
            . '\x{FE0E}-\x{FE0F}'
            . '\x{FEFF}'
            . '\x{00AD}'
            . ']/u',
            '',
            $name
        ) ?? $name;

        /*
         * Lowercase.
         */
        $name = mb_strtolower(
            $name,
            'UTF-8'
        );

        /*
         * Cyrillic -> Latin.
         */
        $name =
            $this->transliterate(
                $name
            );

        /*
         * Remove Unicode accents/combining marks.
         */
        $name =
            $this->stripDiacritics(
                $name
            );

        /*
         * Normalize Uzbek apostrophe variants.
         */
        $name = str_replace(
            [
                "'",
                '’',
                '‘',
                '′',
                '`',
                'ʻ',
                'ʼ',
                'ʹ',
                'ʺ',
            ],
            '',
            $name
        );

        /*
         * Orthographic normalization.
         *
         * kh / h / x → h
         * q / k → k
         * w → v
         * gh / gʻ / ғ → g
         */
        $name = str_replace(
            [
                'kh',
                'x',
            ],
            [
                'h',
                'h',
            ],
            $name
        );

        $name = str_replace(
            [
                'gh',
                'gʻ',
                'g‘',
                'gʼ',
                'ғ',
            ],
            'g',
            $name
        );

        $name = str_replace(
            [
                'q',
            ],
            'k',
            $name
        );

        $name = str_replace(
            [
                'w',
            ],
            'v',
            $name
        );

        /*
         * Uzbek vowels.
         */
        $name = str_replace(
            [
                'oʻ',
                'o‘',
                'oʼ',
                'ў',
            ],
            'u',
            $name
        );

        $name = str_replace(
            [
                'ў',
            ],
            'u',
            $name
        );

        /*
         * Remove all remaining decorative symbols.
         *
         * At this point letters extracted from styles remain.
         */
        $name = preg_replace(
            '/[^a-z0-9\s]/u',
            ' ',
            $name
        ) ?? $name;

        /*
         * Collapse spaces.
         */
        $name = preg_replace(
            '/\s+/u',
            ' ',
            $name
        ) ?? $name;

        return trim($name);
    }

    private function tokens(
        string $name
    ): array {
        $normalized =
            $this->normalizeName(
                $name
            );

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split(
            '/\s+/u',
            $normalized
        );

        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];

        foreach ($parts as $token) {
            $token = trim(
                (string) $token
            );

            if ($token === '') {
                continue;
            }

            if (
                mb_strlen($token)
                < self::MIN_TOKEN_LENGTH
            ) {
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(
            array_unique($tokens)
        );
    }

    /*
     * ============================================================
     * UNICODE STYLE DECODER
     * ============================================================
     */

    private function decodeStyledCharacters(
        string $value
    ): string {
        $characters = preg_split(
            '//u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($characters)) {
            return $value;
        }

        $result = '';

        foreach ($characters as $character) {
            $codePoint =
                $this->codePoint(
                    $character
                );

            if ($codePoint === null) {
                $result .= $character;
                continue;
            }

            /*
             * ----------------------------------------------------
             * Enclosed / squared Latin:
             *
             * 🅰 🅱 🅲 ...
             * 🅸 🅻 🆈 ...
             *
             * U+1F170 - U+1F189
             * ----------------------------------------------------
             */
            $mapped =
                $this->mapNegativeSquaredLatin(
                    $codePoint
                );

            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            /*
             * ----------------------------------------------------
             * Regional / fullwidth / circled variants.
             * ----------------------------------------------------
             */
            $mapped =
                $this->mapBasicStyledLatin(
                    $codePoint
                );

            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            /*
             * ----------------------------------------------------
             * Mathematical alphanumeric symbols.
             * ----------------------------------------------------
             */
            $mapped =
                $this->mapMathematicalLatin(
                    $codePoint
                );

            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            $result .= $character;
        }

        return $result;
    }

    private function mapNegativeSquaredLatin(
        int $codePoint
    ): ?string {
        /*
         * NEGATIVE SQUARED LATIN CAPITAL LETTERS
         *
         * 🅰 U+1F170
         * 🅱 U+1F171
         * ...
         * 🆉 U+1F189
         */
        if (
            $codePoint >= 0x1F170
            && $codePoint <= 0x1F189
        ) {
            return chr(
                0x41
                + (
                    $codePoint - 0x1F170
                )
            );
        }

        return null;
    }

    private function mapBasicStyledLatin(
        int $codePoint
    ): ?string {
        /*
         * Fullwidth uppercase.
         */
        if (
            $codePoint >= 0xFF21
            && $codePoint <= 0xFF3A
        ) {
            return chr(
                0x41
                + (
                    $codePoint - 0xFF21
                )
            );
        }

        /*
         * Fullwidth lowercase.
         */
        if (
            $codePoint >= 0xFF41
            && $codePoint <= 0xFF5A
        ) {
            return chr(
                0x61
                + (
                    $codePoint - 0xFF41
                )
            );
        }

        /*
         * Circled uppercase:
         * Ⓐ Ⓑ ...
         */
        if (
            $codePoint >= 0x24B6
            && $codePoint <= 0x24CF
        ) {
            return chr(
                0x41
                + (
                    $codePoint - 0x24B6
                )
            );
        }

        /*
         * Circled lowercase:
         * ⓐ ⓑ ...
         */
        if (
            $codePoint >= 0x24D0
            && $codePoint <= 0x24E9
        ) {
            return chr(
                0x61
                + (
                    $codePoint - 0x24D0
                )
            );
        }

        /*
         * Parenthesized lowercase.
         */
        if (
            $codePoint >= 0x249C
            && $codePoint <= 0x24B5
        ) {
            return chr(
                0x61
                + (
                    $codePoint - 0x249C
                )
            );
        }

        return null;
    }

    private function mapMathematicalLatin(
        int $codePoint
    ): ?string {
        /*
         * Mathematical Latin ranges.
         *
         * A-Z / a-z variants:
         *
         * Bold
         * Italic
         * Bold Italic
         * Script
         * Bold Script
         * Fraktur
         * Double-Struck
         * Bold Fraktur
         * Sans
         * Sans Bold
         * Sans Italic
         * Sans Bold Italic
         * Monospace
         */
        $ranges = [
            [0x1D400, 0x1D419, 'upper'],
            [0x1D41A, 0x1D433, 'lower'],

            [0x1D434, 0x1D44D, 'upper'],
            [0x1D44E, 0x1D467, 'lower'],

            [0x1D468, 0x1D481, 'upper'],
            [0x1D482, 0x1D49B, 'lower'],

            [0x1D49C, 0x1D4B5, 'upper'],
            [0x1D4B6, 0x1D4CF, 'lower'],

            [0x1D4D0, 0x1D4E9, 'upper'],
            [0x1D4EA, 0x1D503, 'lower'],

            [0x1D504, 0x1D51D, 'upper'],
            [0x1D51E, 0x1D537, 'lower'],

            [0x1D538, 0x1D551, 'upper'],
            [0x1D552, 0x1D56B, 'lower'],

            [0x1D56C, 0x1D585, 'upper'],
            [0x1D586, 0x1D59F, 'lower'],

            [0x1D5A0, 0x1D5B9, 'upper'],
            [0x1D5BA, 0x1D5D3, 'lower'],

            [0x1D5D4, 0x1D5ED, 'upper'],
            [0x1D5EE, 0x1D607, 'lower'],

            [0x1D608, 0x1D621, 'upper'],
            [0x1D622, 0x1D63B, 'lower'],

            [0x1D63C, 0x1D655, 'upper'],
            [0x1D656, 0x1D66F, 'lower'],

            [0x1D670, 0x1D689, 'upper'],
            [0x1D68A, 0x1D6A3, 'lower'],
        ];

        foreach ($ranges as [
            $start,
            $end,
            $type,
        ]) {
            if (
                $codePoint < $start
                || $codePoint > $end
            ) {
                continue;
            }

            $offset =
                $codePoint - $start;

            return $type === 'upper'
                ? chr(0x41 + $offset)
                : chr(0x61 + $offset);
        }

        /*
         * Mathematical digits.
         */
        $digitRanges = [
            [0x1D7CE, 0x1D7D7],
            [0x1D7D8, 0x1D7E1],
            [0x1D7E2, 0x1D7EB],
            [0x1D7EC, 0x1D7F5],
            [0x1D7F6, 0x1D7FF],
        ];

        foreach ($digitRanges as [
            $start,
            $end,
        ]) {
            if (
                $codePoint >= $start
                && $codePoint <= $end
            ) {
                return chr(
                    0x30
                    + (
                        $codePoint - $start
                    )
                );
            }
        }

        return null;
    }

    /*
     * ============================================================
     * ORTHOGRAPHIC NORMALIZATION
     * ============================================================
     */

    private function compactToken(
        string $token
    ): string {
        $token =
            $this->normalizeName(
                $token
            );

        return str_replace(
            [
                'aa',
                'ee',
                'ii',
                'oo',
                'uu',
            ],
            [
                'a',
                'e',
                'i',
                'o',
                'u',
            ],
            $token
        );
    }

    private function phoneticForm(
        string $token
    ): string {
        $token =
            $this->compactToken(
                $token
            );

        return str_replace(
            [
                'kh',
                'gh',
                'ck',
                'qu',
                'oo',
                'ee',
                'aa',
                'x',
                'q',
                'w',
            ],
            [
                'h',
                'g',
                'k',
                'k',
                'o',
                'e',
                'a',
                'h',
                'k',
                'v',
            ],
            $token
        );
    }

    /*
     * ============================================================
     * STRING SIMILARITY
     * ============================================================
     */

    private function stringSimilarity(
        string $expected,
        string $actual,
    ): float {
        if ($expected === $actual) {
            return 100.0;
        }

        if (
            $expected === ''
            || $actual === ''
        ) {
            return 0.0;
        }

        similar_text(
            $expected,
            $actual,
            $similarTextPercent
        );

        $maxLength = max(
            strlen($expected),
            strlen($actual)
        );

        $distance = levenshtein(
            $expected,
            $actual
        );

        $levenshteinScore =
            $maxLength > 0
            ? max(
                0.0,
                100.0
                * (
                    1.0
                    - (
                        $distance
                        / $maxLength
                    )
                )
            )
            : 0.0;

        $prefixScore =
            $this->commonPrefixScore(
                $expected,
                $actual
            );

        return round(
            (
                ((float) $similarTextPercent * 0.45)
                + ($levenshteinScore * 0.40)
                + ($prefixScore * 0.15)
            ),
            2
        );
    }

    private function commonPrefixScore(
        string $a,
        string $b
    ): float {
        $length = min(
            strlen($a),
            strlen($b)
        );

        if ($length === 0) {
            return 0.0;
        }

        $prefix = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($a[$i] !== $b[$i]) {
                break;
            }

            $prefix++;
        }

        return round(
            (
                $prefix
                / max(
                    strlen($a),
                    strlen($b)
                )
            ) * 100,
            2
        );
    }

    /*
     * ============================================================
     * REASONS / OUTPUT
     * ============================================================
     */

    private function buildReasons(
        array $expectedTokens,
        array $telegramTokens,
        array $matches,
        float $score,
        bool $matched,
    ): array {
        $reasons = [];

        if ($matched) {
            $reasons[] =
                'strong_name_match';
        }

        foreach ($matches as $match) {
            $reason = $match['reason'] ?? null;

            if (
                $reason === 'exact'
                || $reason === 'canonical_equal'
            ) {
                $reasons[] =
                    'exact_token_match';

                break;
            }
        }

        foreach ($matches as $match) {
            if (
                ($match['reason'] ?? null)
                === 'phonetic_equal'
            ) {
                $reasons[] =
                    'phonetic_match';

                break;
            }
        }

        foreach ($matches as $match) {
            if (
                ($match['reason'] ?? null)
                === 'contains'
            ) {
                $reasons[] =
                    'partial_token_match';

                break;
            }
        }

        if (
            count($telegramTokens)
            < count($expectedTokens)
        ) {
            $reasons[] =
                'telegram_name_is_shorter';
        }

        if (
            count($telegramTokens)
            > count($expectedTokens)
        ) {
            $reasons[] =
                'telegram_name_contains_extra_tokens';
        }

        if (
            $score < 55
            && $matches !== []
        ) {
            $reasons[] =
                'insufficient_similarity';
        }

        return array_values(
            array_unique($reasons)
        );
    }

    private function result(
        string $expectedName,
        string $telegramName,
        string $normalizedExpected,
        string $normalizedTelegram,
        bool $matched,
        float $score,
        string $level,
        array $matches,
        array $expectedTokens,
        array $telegramTokens,
        array $reasons,
        float $weightedTokenScore = 0.0,
        float $fullNameSimilarity = 0.0,
        float $partialScore = 0.0,
        float $multiTokenScore = 0.0,
        float $identityScore = 0.0,
        ?int $matchedTokenCount = null,
        ?int $usefulTokenCount = null,
    ): array {
        $strongMatches = array_values(
            array_filter(
                $matches,
                static fn(array $match): bool =>
                (float) $match['score']
                >= self::STRONG_TOKEN_SCORE
            )
        );

        $usefulMatches = array_values(
            array_filter(
                $matches,
                static fn(array $match): bool =>
                (float) $match['score']
                >= self::USEFUL_TOKEN_SCORE
            )
        );

        return [
            'matched' => $matched,

            'score' => round(
                $score,
                2
            ),

            'level' => $level,

            'expected_name' =>
                $expectedName,

            'telegram_name' =>
                $telegramName,

            /*
             * These fields are useful for debugging.
             */
            'normalized_expected' =>
                $normalizedExpected,

            'normalized_telegram' =>
                $normalizedTelegram,

            'matched_tokens' =>
                array_values(
                    array_slice(
                        $strongMatches,
                        0,
                        self::MAX_MATCHES
                    )
                ),

            'possible_tokens' =>
                array_values(
                    array_slice(
                        $matches,
                        0,
                        self::MAX_MATCHES
                    )
                ),

            'matched_token_count' =>
                $matchedTokenCount
                ?? count($strongMatches),

            'useful_token_count' =>
                $usefulTokenCount
                ?? count($usefulMatches),

            'expected_token_count' =>
                count($expectedTokens),

            'telegram_token_count' =>
                count($telegramTokens),

            'match_ratio' =>
                count($expectedTokens) > 0
                ? round(
                    count($strongMatches)
                    / count($expectedTokens),
                    4
                )
                : 0.0,

            'best_token_score' =>
                $matches !== []
                ? round(
                    max(
                        array_map(
                            static fn(
                            array $match
                        ): float =>
                            (float) $match['score'],
                            $matches
                        )
                    ),
                    2
                )
                : 0.0,

            'weighted_token_score' =>
                round(
                    $weightedTokenScore,
                    2
                ),

            'full_name_similarity' =>
                round(
                    $fullNameSimilarity,
                    2
                ),

            'partial_name_bonus' =>
                round(
                    $partialScore,
                    2
                ),

            'multi_token_bonus' =>
                round(
                    $multiTokenScore,
                    2
                ),

            'identity_token_bonus' =>
                round(
                    $identityScore,
                    2
                ),

            'reasons' =>
                $reasons,
        ];
    }

    private function noDataResult(
        ?string $expectedName,
        ?string $telegramName,
    ): array {
        return [
            'matched' => false,
            'score' => 0.0,
            'level' => 'no_data',

            'expected_name' => $expectedName,
            'telegram_name' => $telegramName,

            'normalized_expected' =>
                $expectedName !== null
                ? $this->normalizeName(
                    $expectedName
                )
                : null,

            'normalized_telegram' =>
                $telegramName !== null
                ? $this->normalizeName(
                    $telegramName
                )
                : null,

            'matched_tokens' => [],
            'possible_tokens' => [],

            'matched_token_count' => 0,
            'useful_token_count' => 0,

            'expected_token_count' => 0,
            'telegram_token_count' => 0,

            'match_ratio' => 0.0,

            'best_token_score' => 0.0,
            'weighted_token_score' => 0.0,
            'full_name_similarity' => 0.0,
            'partial_name_bonus' => 0.0,
            'multi_token_bonus' => 0.0,
            'identity_token_bonus' => 0.0,

            'reasons' => [
                'missing_name_data',
            ],
        ];
    }

    /*
     * ============================================================
     * UTILS
     * ============================================================
     */

    private function isBlank(
        ?string $value
    ): bool {
        return $value === null
            || trim($value) === '';
    }

    private function stripDiacritics(
        string $value
    ): string {
        if (
            class_exists(\Normalizer::class)
        ) {
            $normalized =
                \Normalizer::normalize(
                    $value,
                    \Normalizer::FORM_D
                );

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return preg_replace(
            '/\p{Mn}+/u',
            '',
            $value
        ) ?? $value;
    }

    private function transliterate(
        string $value
    ): string {
        return strtr(
            $value,
            [
                'а' => 'a',
                'б' => 'b',
                'в' => 'v',
                'г' => 'g',
                'д' => 'd',
                'е' => 'e',
                'ё' => 'yo',
                'ж' => 'j',
                'з' => 'z',
                'и' => 'i',
                'й' => 'i',
                'к' => 'k',
                'л' => 'l',
                'м' => 'm',
                'н' => 'n',
                'о' => 'o',
                'п' => 'p',
                'р' => 'r',
                'с' => 's',
                'т' => 't',
                'у' => 'u',
                'ф' => 'f',
                'х' => 'h',
                'ц' => 'c',
                'ч' => 'ch',
                'ш' => 'sh',
                'щ' => 'sh',
                'ы' => 'i',
                'э' => 'e',
                'ю' => 'yu',
                'я' => 'ya',

                'ў' => 'u',
                'қ' => 'q',
                'ғ' => 'g',
                'ҳ' => 'h',
            ]
        );
    }

    private function codePoint(
        string $character
    ): ?int {
        $bytes = unpack(
            'C*',
            $character
        );

        if (!is_array($bytes)) {
            return null;
        }

        $bytes = array_values($bytes);

        $first = $bytes[0] ?? null;

        if ($first === null) {
            return null;
        }

        /*
         * ASCII
         */
        if ($first <= 0x7F) {
            return $first;
        }

        /*
         * 2-byte UTF-8.
         */
        if (
            ($first & 0xE0) === 0xC0
            && isset($bytes[1])
        ) {
            return (
                (($first & 0x1F) << 6)
                | ($bytes[1] & 0x3F)
            );
        }

        /*
         * 3-byte UTF-8.
         */
        if (
            ($first & 0xF0) === 0xE0
            && isset(
            $bytes[1],
            $bytes[2]
        )
        ) {
            return (
                (($first & 0x0F) << 12)
                | (($bytes[1] & 0x3F) << 6)
                | ($bytes[2] & 0x3F)
            );
        }

        /*
         * 4-byte UTF-8.
         */
        if (
            ($first & 0xF8) === 0xF0
            && isset(
            $bytes[1],
            $bytes[2],
            $bytes[3]
        )
        ) {
            return (
                (($first & 0x07) << 18)
                | (($bytes[1] & 0x3F) << 12)
                | (($bytes[2] & 0x3F) << 6)
                | ($bytes[3] & 0x3F)
            );
        }

        return null;
    }
}