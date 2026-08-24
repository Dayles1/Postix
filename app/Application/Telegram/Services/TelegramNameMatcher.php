<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

final class TelegramNameMatcher
{
    private const EXACT_SCORE = 100.0;

    /**
     * A token with the same characters in the same order,
     * but with extra/missing characters, gives a strong signal.
     */
    private const ORDERED_SCORE = 70.0;

    /**
     * Phonetic similarity is weaker than ordered identity.
     */
    private const PHONETIC_SCORE = 60.0;

    /**
     * Fuzzy similarity is only fallback evidence.
     */
    private const FUZZY_MIN_SCORE = 40.0;

    private const CONFIRM_SCORE = 70.0;

    private const MIN_TOKEN_LENGTH = 2;

    private const MAX_MATCHES = 100;

    /**
     * Tokens that are usually not identity-bearing by themselves.
     *
     * IMPORTANT:
     *
     * We do NOT remove these from the nickname.
     *
     * They are only treated as weak/noise when they are the
     * ONLY token being compared.
     *
     * Example:
     *
     * "Nozim bek"
     *
     * Nozim -> identity evidence.
     * bek   -> extra nickname part.
     */
    private const WEAK_STANDALONE_TOKENS = [
        'bek',
        'beg',
        'jon',
        'jan',
        'hon',
        'xon',
        'boy',
        'aka',
        'opa',
        'uka',
        'bro',
        'sis',
    ];

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
                    static fn ($value): bool =>
                        $value !== null
                        && trim((string) $value) !== '',
                ),
            ),
        );

        if (
            $this->isBlank($expectedName)
            || $this->isBlank($telegramFullName)
        ) {
            return $this->noDataResult(
                $expectedName,
                $telegramFullName !== ''
                    ? $telegramFullName
                    : null,
            );
        }

        $normalizedExpected = $this->normalizeName(
            $expectedName,
        );

        $normalizedTelegram = $this->normalizeName(
            $telegramFullName,
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

        $expectedTokens = $this->tokens(
            $normalizedExpected,
        );

        $telegramTokens = $this->tokens(
            $normalizedTelegram,
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
         * ---------------------------------------------------------
         * EXACT FULL NAME
         * ---------------------------------------------------------
         */
        if (
            $normalizedExpected === $normalizedTelegram
        ) {
            $matches = $this->buildTokenMatches(
                $expectedTokens,
                $telegramTokens,
            );

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
                    'strong_name_match',
                ],
                weightedTokenScore: 100.0,
                fullNameSimilarity: 100.0,
                partialScore: 100.0,
                multiTokenScore: 100.0,
                identityScore: 100.0,
                matchedTokenCount: count($telegramTokens),
                usefulTokenCount: count($telegramTokens),
            );
        }

        /*
         * ---------------------------------------------------------
         * TOKEN MATCHING
         * ---------------------------------------------------------
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

        /*
         * ---------------------------------------------------------
         * MAIN BUSINESS RULE
         * ---------------------------------------------------------
         *
         * We do NOT average all Telegram tokens.
         *
         * We search for the BEST identity evidence.
         *
         * This is important because Telegram nickname can be:
         *
         *   Tenison
         *   Roberta Carlos
         *   Nozim bek
         *
         * and extra nickname words must not destroy a good match.
         */
        $bestIdentityMatch = $this->bestIdentityMatch(
            $matches,
        );

        if ($bestIdentityMatch === null) {
            return $this->result(
                expectedName: $expectedName,
                telegramName: $telegramFullName,
                normalizedExpected: $normalizedExpected,
                normalizedTelegram: $normalizedTelegram,
                matched: false,
                score: 0.0,
                level: 'none',
                matches: $matches,
                expectedTokens: $expectedTokens,
                telegramTokens: $telegramTokens,
                reasons: [
                    'no_identity_match',
                ],
            );
        }

        $score = (float) (
            $bestIdentityMatch['score'] ?? 0.0
        );

        /*
         * ---------------------------------------------------------
         * MULTIPLE IDENTITY TOKENS
         * ---------------------------------------------------------
         *
         * If several meaningful Telegram tokens independently
         * match expected identity, confidence increases.
         *
         * Exact + exact -> 100
         * Ordered + ordered -> 85
         * Exact + ordered -> 100
         */
        $identityMatches = array_values(
            array_filter(
                $matches,
                fn (array $match): bool =>
                    $this->isIdentityEvidence($match),
            ),
        );

        if (
            $this->hasExactIdentityMatch(
                $identityMatches,
            )
        ) {
            $score = 100.0;
        } elseif (
            count($identityMatches) >= 2
        ) {
            $scores = array_map(
                static fn(array $match): float =>
                    (float) (
                        $match['score'] ?? 0.0
                    ),
                $identityMatches,
            );

            /*
             * Two independent ordered identity signals
             * are stronger than one.
             */
            if (
                count(
                    array_filter(
                        $scores,
                        static fn(float $value): bool =>
                            $value >= self::ORDERED_SCORE,
                    ),
                ) >= 2
            ) {
                $score = max(
                    $score,
                    85.0,
                );
            }
        }

        /*
         * Clamp.
         */
        $score = round(
            min(
                100.0,
                max(
                    0.0,
                    $score,
                ),
            ),
            2,
        );

        $matched = $this->isConfirmed(
            $score,
            $bestIdentityMatch,
        );

        $level = $this->resolveLevel(
            $score,
            $matched,
        );

        $matchedTokenCount = count(
            $identityMatches,
        );

        $usefulMatches = array_values(
            array_filter(
                $matches,
                static fn(array $match): bool =>
                    (float) (
                        $match['score'] ?? 0.0
                    ) >= self::FUZZY_MIN_SCORE,
            ),
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
            reasons: $this->buildReasons(
                $matches,
                $bestIdentityMatch,
                $matched,
            ),
            weightedTokenScore: $score,
            fullNameSimilarity: $score,
            partialScore: $score,
            multiTokenScore: count($identityMatches) >= 2
                ? 85.0
                : $score,
            identityScore: $score,
            matchedTokenCount: $matchedTokenCount,
            usefulTokenCount: count($usefulMatches),
        );
    }

    /**
     * ------------------------------------------------------------
     * BUILD BEST MATCH FOR EVERY TELEGRAM TOKEN
     * ------------------------------------------------------------
     */
    private function buildTokenMatches(
        array $expectedTokens,
        array $telegramTokens,
    ): array {
        $matches = [];

        foreach (
            $telegramTokens as $actualIndex => $actualToken
        ) {
            $bestMatch = null;

            foreach (
                $expectedTokens as $expectedIndex => $expectedToken
            ) {
                $comparison = $this->tokenSimilarity(
                    $expectedToken,
                    $actualToken,
                );

                $candidate = [
                    'expected' => $expectedToken,
                    'actual' => $actualToken,
                    'score' => $comparison['score'],
                    'reason' => $comparison['reason'],
                    'expected_index' => $expectedIndex,
                    'actual_index' => $actualIndex,
                    'actual_is_weak_word' =>
                        $this->isWeakStandaloneToken(
                            $actualToken,
                        ),
                ];

                if (
                    $bestMatch === null
                    || (
                        (float) $candidate['score']
                        > (float) $bestMatch['score']
                    )
                ) {
                    $bestMatch = $candidate;
                }
            }

            if ($bestMatch === null) {
                continue;
            }

            /*
             * Completely irrelevant token.
             */
            if (
                (float) $bestMatch['score']
                < self::FUZZY_MIN_SCORE
            ) {
                continue;
            }

            $matches[] = $bestMatch;
        }

        usort(
            $matches,
            static fn(
                array $a,
                array $b,
            ): int =>
                $b['score'] <=> $a['score'],
        );

        return array_slice(
            $matches,
            0,
            self::MAX_MATCHES,
        );
    }

    /**
     * ------------------------------------------------------------
     * TOKEN SIMILARITY
     * ------------------------------------------------------------
     */
    private function tokenSimilarity(
        string $expected,
        string $actual,
    ): array {
        $expected = $this->compactToken(
            $expected,
        );

        $actual = $this->compactToken(
            $actual,
        );

        /*
         * ---------------------------------------------------------
         * EXACT
         * ---------------------------------------------------------
         */
        if ($expected === $actual) {
            return [
                'score' => self::EXACT_SCORE,
                'reason' => 'exact',
            ];
        }

        /*
         * ---------------------------------------------------------
         * ORDERED CHARACTERS
         * ---------------------------------------------------------
         *
         * This is the key rule.
         *
         * Examples:
         *
         * TEN
         * TENISON
         *
         * TEN is inside TENISON in the same order.
         *
         * Result = 70.
         *
         * ROBERT
         * ROBERTA
         *
         * Result = 70.
         *
         * NOZIM
         * NOZIMJON
         *
         * Result = 70.
         */
        if (
            $this->isSubsequence(
                $actual,
                $expected,
            )
        ) {
            return [
                'score' => self::ORDERED_SCORE,
                'reason' => 'ordered_subsequence',
            ];
        }

        if (
            $this->isSubsequence(
                $expected,
                $actual,
            )
        ) {
            return [
                'score' => self::ORDERED_SCORE,
                'reason' => 'ordered_contains',
            ];
        }

        /*
         * ---------------------------------------------------------
         * PHONETIC
         * ---------------------------------------------------------
         */
        $expectedPhonetic = $this->phoneticForm(
            $expected,
        );

        $actualPhonetic = $this->phoneticForm(
            $actual,
        );

        if (
            $expectedPhonetic !== ''
            && $expectedPhonetic === $actualPhonetic
        ) {
            return [
                'score' => self::PHONETIC_SCORE,
                'reason' => 'phonetic_equal',
            ];
        }

        /*
         * ---------------------------------------------------------
         * FUZZY
         * ---------------------------------------------------------
         */
        return [
            'score' => $this->stringSimilarity(
                $expected,
                $actual,
            ),
            'reason' => 'fuzzy',
        ];
    }

    /**
     * ------------------------------------------------------------
     * BEST IDENTITY MATCH
     * ------------------------------------------------------------
     */
    private function bestIdentityMatch(
        array $matches,
    ): ?array {
        $best = null;

        foreach ($matches as $match) {
            /*
             * A standalone "bek", "jon", etc. should not
             * be sufficient by itself to identify a person.
             */
            if (
                $this->isWeakStandaloneToken(
                    (string) (
                        $match['actual'] ?? ''
                    ),
                )
                && (float) (
                    $match['score'] ?? 0.0
                ) < self::EXACT_SCORE
            ) {
                continue;
            }

            /*
             * Identity evidence starts at 70.
             */
            if (
                (float) (
                    $match['score'] ?? 0.0
                ) < self::ORDERED_SCORE
            ) {
                continue;
            }

            if (
                $best === null
                || (
                    (float) $match['score']
                    > (float) $best['score']
                )
            ) {
                $best = $match;
            }
        }

        return $best;
    }

    /**
     * ------------------------------------------------------------
     * IDENTITY HELPERS
     * ------------------------------------------------------------
     */
    private function isIdentityEvidence(
        array $match,
    ): bool {
        $score = (float) (
            $match['score'] ?? 0.0
        );

        if ($score < self::ORDERED_SCORE) {
            return false;
        }

        $actual = (string) (
            $match['actual'] ?? ''
        );

        /*
         * Weak standalone nick words are not identity evidence.
         */
        if (
            $this->isWeakStandaloneToken($actual)
            && $score < self::EXACT_SCORE
        ) {
            return false;
        }

        return true;
    }

    private function hasExactIdentityMatch(
        array $matches,
    ): bool {
        foreach ($matches as $match) {
            if (
                !$this->isIdentityEvidence($match)
            ) {
                continue;
            }

            if (
                (float) (
                    $match['score'] ?? 0.0
                )
                >= self::EXACT_SCORE
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * ------------------------------------------------------------
     * CONFIRMATION
     * ------------------------------------------------------------
     */
    private function isConfirmed(
        float $score,
        array $bestIdentityMatch,
    ): bool {
        if ($score >= self::EXACT_SCORE) {
            return true;
        }

        if ($score >= self::CONFIRM_SCORE) {
            return true;
        }

        return (
            (float) (
                $bestIdentityMatch['score'] ?? 0.0
            )
            >= self::ORDERED_SCORE
        );
    }

    /**
     * ------------------------------------------------------------
     * LEVEL
     * ------------------------------------------------------------
     */
    private function resolveLevel(
        float $score,
        bool $matched,
    ): string {
        if ($score >= 100.0) {
            return 'exact';
        }

        if ($score >= 85.0) {
            return 'very_high';
        }

        if ($score >= 70.0) {
            return 'high';
        }

        if ($score >= 60.0) {
            return 'medium';
        }

        if ($score >= 40.0) {
            return 'low';
        }

        return $matched
            ? 'low'
            : 'very_low';
    }

    /**
     * ------------------------------------------------------------
     * WEAK NICKNAME TOKENS
     * ------------------------------------------------------------
     */
    private function isWeakStandaloneToken(
        string $token,
    ): bool {
        return in_array(
            $this->compactToken($token),
            self::WEAK_STANDALONE_TOKENS,
            true,
        );
    }

    /**
     * ------------------------------------------------------------
     * REASONS
     * ------------------------------------------------------------
     */
    private function buildReasons(
        array $matches,
        ?array $bestIdentityMatch,
        bool $matched,
    ): array {
        $reasons = [];

        if ($matched) {
            $reasons[] = 'identity_match';
        }

        if ($bestIdentityMatch !== null) {
            $reason =
                $bestIdentityMatch['reason']
                ?? null;

            if ($reason === 'exact') {
                $reasons[] = 'exact_token_match';
            }

            if (
                $reason === 'ordered_subsequence'
                || $reason === 'ordered_contains'
            ) {
                $reasons[] = 'ordered_character_match';
            }

            if ($reason === 'phonetic_equal') {
                $reasons[] = 'phonetic_match';
            }

            if ($reason === 'fuzzy') {
                $reasons[] = 'fuzzy_match';
            }
        }

        foreach ($matches as $match) {
            if (
                $this->isWeakStandaloneToken(
                    (string) (
                        $match['actual'] ?? ''
                    ),
                )
            ) {
                $reasons[] = 'nickname_suffix_or_extra_word';
                break;
            }
        }

        return array_values(
            array_unique($reasons),
        );
    }

    /**
     * ------------------------------------------------------------
     * SUBSEQUENCE
     * ------------------------------------------------------------
     *
     * Every character in $needle must occur in $haystack
     * in the same order.
     *
     * Length does not matter.
     */
    private function isSubsequence(
        string $needle,
        string $haystack,
    ): bool {
        if ($needle === '') {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        $needleChars = preg_split(
            '//u',
            $needle,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        $haystackChars = preg_split(
            '//u',
            $haystack,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (
            !is_array($needleChars)
            || !is_array($haystackChars)
        ) {
            return false;
        }

        $needleIndex = 0;
        $needleCount = count(
            $needleChars,
        );

        foreach ($haystackChars as $char) {
            if (
                isset(
                    $needleChars[$needleIndex],
                )
                && $char
                    === $needleChars[$needleIndex]
            ) {
                $needleIndex++;

                if ($needleIndex >= $needleCount) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ------------------------------------------------------------
     * RESULT
     * ------------------------------------------------------------
     */
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
        $identityMatches = array_values(
            array_filter(
                $matches,
                fn(array $match): bool =>
                    $this->isIdentityEvidence($match),
            ),
        );

        return [
            'matched' => $matched,

            'score' => round(
                $score,
                2,
            ),

            'level' => $level,

            'expected_name' =>
                $expectedName,

            'telegram_name' =>
                $telegramName,

            'normalized_expected' =>
                $normalizedExpected,

            'normalized_telegram' =>
                $normalizedTelegram,

            'matched_tokens' =>
                array_values(
                    array_slice(
                        $identityMatches,
                        0,
                        self::MAX_MATCHES,
                    ),
                ),

            'possible_tokens' =>
                array_values(
                    array_slice(
                        $matches,
                        0,
                        self::MAX_MATCHES,
                    ),
                ),

            'matched_token_count' =>
                $matchedTokenCount
                ?? count($identityMatches),

            'useful_token_count' =>
                $usefulTokenCount
                ?? count($matches),

            'expected_token_count' =>
                count($expectedTokens),

            'telegram_token_count' =>
                count($telegramTokens),

            /*
             * IMPORTANT:
             *
             * Ratio is not used as the final score.
             *
             * It is only diagnostic.
             */
            'match_ratio' =>
                count($telegramTokens) > 0
                ? round(
                    count($identityMatches)
                    / count($telegramTokens),
                    4,
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
                                (float) (
                                    $match['score']
                                    ?? 0.0
                                ),
                            $matches,
                        ),
                    ),
                    2,
                )
                : 0.0,

            'weighted_token_score' =>
                round(
                    $weightedTokenScore,
                    2,
                ),

            'full_name_similarity' =>
                round(
                    $fullNameSimilarity,
                    2,
                ),

            'partial_name_bonus' =>
                round(
                    $partialScore,
                    2,
                ),

            'multi_token_bonus' =>
                round(
                    $multiTokenScore,
                    2,
                ),

            'identity_token_bonus' =>
                round(
                    $identityScore,
                    2,
                ),

            'reasons' =>
                $reasons,
        ];
    }

    /**
     * ------------------------------------------------------------
     * NO DATA
     * ------------------------------------------------------------
     */
    private function noDataResult(
        ?string $expectedName,
        ?string $telegramName,
    ): array {
        return [
            'matched' => false,

            'score' => 0.0,

            'level' => 'no_data',

            'expected_name' =>
                $expectedName,

            'telegram_name' =>
                $telegramName,

            'normalized_expected' =>
                $expectedName !== null
                ? $this->normalizeName(
                    $expectedName,
                )
                : null,

            'normalized_telegram' =>
                $telegramName !== null
                ? $this->normalizeName(
                    $telegramName,
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

    /**
     * ------------------------------------------------------------
     * NORMALIZATION
     * ------------------------------------------------------------
     */
    private function normalizeName(
        string $name,
    ): string {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize(
                $name,
                \Normalizer::FORM_KC,
            );

            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $name = $this->decodeStyledCharacters(
            $name,
        );

        $name = preg_replace(
            '/[\x{200B}-\x{200D}\x{2060}\x{FE0E}-\x{FE0F}\x{FEFF}\x{00AD}]/u',
            '',
            $name,
        ) ?? $name;

        $name = mb_strtolower(
            $name,
            'UTF-8',
        );

        $name = $this->transliterate(
            $name,
        );

        $name = $this->stripDiacritics(
            $name,
        );

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
            $name,
        );

        /*
         * Orthographic normalization.
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
            $name,
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
            $name,
        );

        $name = str_replace(
            [
                'q',
            ],
            'k',
            $name,
        );

        $name = str_replace(
            [
                'w',
            ],
            'v',
            $name,
        );

        $name = str_replace(
            [
                'oʻ',
                'o‘',
                'oʼ',
                'ў',
            ],
            'u',
            $name,
        );

        $name = preg_replace(
            '/[^a-z0-9\s]/u',
            ' ',
            $name,
        ) ?? $name;

        $name = preg_replace(
            '/\s+/u',
            ' ',
            $name,
        ) ?? $name;

        return trim($name);
    }

    private function tokens(
        string $name,
    ): array {
        $normalized = $this->normalizeName(
            $name,
        );

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split(
            '/\s+/u',
            $normalized,
        );

        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];

        foreach ($parts as $token) {
            $token = trim(
                (string) $token,
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
            array_unique($tokens),
        );
    }

    /**
     * ------------------------------------------------------------
     * COMPACT TOKEN
     * ------------------------------------------------------------
     */
    private function compactToken(
        string $token,
    ): string {
        $token = $this->normalizeName(
            $token,
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
            $token,
        );
    }

    /**
     * ------------------------------------------------------------
     * PHONETIC
     * ------------------------------------------------------------
     */
    private function phoneticForm(
        string $token,
    ): string {
        $token = $this->compactToken(
            $token,
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
            $token,
        );
    }

    /**
     * ------------------------------------------------------------
     * FUZZY
     * ------------------------------------------------------------
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
            $similarTextPercent,
        );

        $maxLength = max(
            strlen($expected),
            strlen($actual),
        );

        $distance = levenshtein(
            $expected,
            $actual,
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
                    ),
                )
                : 0.0;

        $prefixScore =
            $this->commonPrefixScore(
                $expected,
                $actual,
            );

        return round(
            (
                ((float) $similarTextPercent * 0.45)
                + ($levenshteinScore * 0.40)
                + ($prefixScore * 0.15)
            ),
            2,
        );
    }

    private function commonPrefixScore(
        string $a,
        string $b,
    ): float {
        $length = min(
            strlen($a),
            strlen($b),
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
                    strlen($b),
                )
            ) * 100,
            2,
        );
    }

    /**
     * ------------------------------------------------------------
     * UNICODE STYLE DECODER
     * ------------------------------------------------------------
     */
    private function decodeStyledCharacters(
        string $value,
    ): string {
        $characters = preg_split(
            '//u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (!is_array($characters)) {
            return $value;
        }

        $result = '';

        foreach ($characters as $character) {
            $codePoint = $this->codePoint(
                $character,
            );

            if ($codePoint === null) {
                $result .= $character;
                continue;
            }

            $mapped =
                $this->mapNegativeSquaredLatin(
                    $codePoint,
                );

            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            $mapped =
                $this->mapBasicStyledLatin(
                    $codePoint,
                );

            if ($mapped !== null) {
                $result .= $mapped;
                continue;
            }

            $mapped =
                $this->mapMathematicalLatin(
                    $codePoint,
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
        int $codePoint,
    ): ?string {
        if (
            $codePoint >= 0x1F170
            && $codePoint <= 0x1F189
        ) {
            return chr(
                0x41
                + (
                    $codePoint
                    - 0x1F170
                )
            );
        }

        return null;
    }

    private function mapBasicStyledLatin(
        int $codePoint,
    ): ?string {
        if (
            $codePoint >= 0xFF21
            && $codePoint <= 0xFF3A
        ) {
            return chr(
                0x41
                + (
                    $codePoint
                    - 0xFF21
                )
            );
        }

        if (
            $codePoint >= 0xFF41
            && $codePoint <= 0xFF5A
        ) {
            return chr(
                0x61
                + (
                    $codePoint
                    - 0xFF41
                )
            );
        }

        if (
            $codePoint >= 0x24B6
            && $codePoint <= 0x24CF
        ) {
            return chr(
                0x41
                + (
                    $codePoint
                    - 0x24B6
                )
            );
        }

        if (
            $codePoint >= 0x24D0
            && $codePoint <= 0x24E9
        ) {
            return chr(
                0x61
                + (
                    $codePoint
                    - 0x24D0
                )
            );
        }

        if (
            $codePoint >= 0x249C
            && $codePoint <= 0x24B5
        ) {
            return chr(
                0x61
                + (
                    $codePoint
                    - 0x249C
                )
            );
        }

        return null;
    }

    private function mapMathematicalLatin(
        int $codePoint,
    ): ?string {
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
                $codePoint
                - $start;

            return $type === 'upper'
                ? chr(0x41 + $offset)
                : chr(0x61 + $offset);
        }

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
                        $codePoint
                        - $start
                    )
                );
            }
        }

        return null;
    }

    /**
     * ------------------------------------------------------------
     * CYRILLIC -> LATIN
     * ------------------------------------------------------------
     */
    private function transliterate(
        string $value,
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
            ],
        );
    }

    /**
     * ------------------------------------------------------------
     * DIACRITICS
     * ------------------------------------------------------------
     */
    private function stripDiacritics(
        string $value,
    ): string {
        if (class_exists(\Normalizer::class)) {
            $normalized =
                \Normalizer::normalize(
                    $value,
                    \Normalizer::FORM_D,
                );

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return preg_replace(
            '/\p{Mn}+/u',
            '',
            $value,
        ) ?? $value;
    }

    /**
     * ------------------------------------------------------------
     * CODEPOINT
     * ------------------------------------------------------------
     */
    private function codePoint(
        string $character,
    ): ?int {
        $bytes = unpack(
            'C*',
            $character,
        );

        if (!is_array($bytes)) {
            return null;
        }

        $bytes = array_values($bytes);

        $first = $bytes[0] ?? null;

        if ($first === null) {
            return null;
        }

        if ($first <= 0x7F) {
            return $first;
        }

        if (
            ($first & 0xE0) === 0xC0
            && isset($bytes[1])
        ) {
            return (
                (($first & 0x1F) << 6)
                | ($bytes[1] & 0x3F)
            );
        }

        if (
            ($first & 0xF0) === 0xE0
            && isset(
                $bytes[1],
                $bytes[2],
            )
        ) {
            return (
                (($first & 0x0F) << 12)
                | (($bytes[1] & 0x3F) << 6)
                | ($bytes[2] & 0x3F)
            );
        }

        if (
            ($first & 0xF8) === 0xF0
            && isset(
                $bytes[1],
                $bytes[2],
                $bytes[3],
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

    private function isBlank(
        ?string $value,
    ): bool {
        return $value === null
            || trim($value) === '';
    }
}