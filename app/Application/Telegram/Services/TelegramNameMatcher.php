<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use Normalizer;

/**
 * Telegram driver-name matcher.
 *
 * This is PROBABILISTIC NAME MATCHING, not identity verification.
 *
 * The question it answers is:
 *
 *     "How likely is it that this Telegram profile (resolved by phone
 *      number) belongs to the driver we have on file?"
 *
 * It is intentionally tolerant of nicknames, partial names, typos and
 * transliteration differences, while still penalizing coincidental
 * overlap of short/common name fragments so it does not turn every
 * "ALI inside KURBONALI" into a strong match.
 *
 * Algorithm (see class-level sections below):
 *
 *  1. Normalize both sides (case, diacritics, emoji, punctuation,
 *     Cyrillic -> Latin transliteration, styled Unicode letters).
 *  2. Split the driver name and the Telegram name/username into
 *     meaningful tokens (bag of tokens, no assumption about field
 *     order/roles).
 *  3. For every driver token, find its single best-matching Telegram
 *     token using three explainable comparison tiers:
 *       - exact / transliteration-variant equality,
 *       - bounded edit-distance ("typo") similarity,
 *       - length-penalized substring ("nickname/partial name") similarity.
 *  4. Combine the best token score with a (much smaller) corroboration
 *     bonus from a second independently-matching token.
 *  5. Map the resulting score onto an explainable level/decision/reason.
 *
 * Public API is kept compatible with the previous implementation:
 *
 *     $matcher->match(
 *         $driverName,
 *         $telegramFirstName,
 *         $telegramLastName,
 *         $telegramUsername,
 *     );
 */
final class TelegramNameMatcher
{
    /* ---------------------------------------------------------------------
     | Configuration
     |--------------------------------------------------------------------- */

    /**
     * Business threshold: score at/above this is treated as "matched".
     */
    private const CONFIRM_THRESHOLD = 75.0;

    /**
     * Level bands. Lower bound is inclusive.
     */
    private const LEVEL_VERY_STRONG = 90.0;
    private const LEVEL_STRONG = 75.0;
    private const LEVEL_LIKELY = 60.0;
    private const LEVEL_POSSIBLE = 40.0;

    /**
     * Score assigned to a literal, already-normalized exact token match.
     */
    private const SCORE_EXACT = 100.0;

    /**
     * Score assigned when two tokens only become equal after canonical
     * transliteration folding (e.g. "zh" <-> "j", "kh" <-> "h").
     */
    private const SCORE_TRANSLITERATION_VARIANT = 96.0;

    /**
     * Minimum token length considered for the "nickname / partial name"
     * containment tier. Anything shorter is too noisy to reason about.
     */
    private const MIN_PARTIAL_TOKEN_LENGTH = 3;

    /**
     * Tokens too generic/common to be strong evidence on their own when
     * they only appear as a short fragment of a longer name.
     */
    private const COMMON_GENERIC_TOKENS = [
        'ali', 'anna', 'max', 'john', 'jon', 'jan', 'bek', 'bek', 'anvar',
        'aka', 'opa', 'uka', 'bro', 'sis', 'boss', 'king', 'mr', 'mrs',
        'miss', 'hon', 'xon', 'boy', 'kamol', 'kamal',
    ];

    /**
     * Patronymic / lineage markers. They carry structure, not identity,
     * and Telegram profiles never contain them.
     */
    private const PATRONYMIC_MARKERS = [
        'ogli', 'ogly', 'oglu', 'uly', 'ugli',
        'qizi', 'kizi', 'qizy',
    ];

    /**
     * Canonical spelling replacements. These fold common Uzbek/Russian/
     * Latin transliteration variance onto a single comparable form.
     * Order matters only in that no two keys overlap in practice.
     */
    private const CANONICAL_REPLACEMENTS = [
        'zh' => 'j',
        'kh' => 'h',
        'xh' => 'h',
        'x' => 'h',
        'ye' => 'e',
        'yev' => 'ev',
        'yova' => 'ova',
        'yovna' => 'ovna',
        'evich' => 'ovich',
        'yevich' => 'ovich',
        'evna' => 'ovna',
        'yevna' => 'ovna',
        'gh' => 'g',
        'q' => 'k',
        'w' => 'v',
        'shch' => 'sh',
        'sch' => 'sh',
    ];

    /* ---------------------------------------------------------------------
     | Public API
     |--------------------------------------------------------------------- */

    public function match(
        ?string $expectedName,
        ?string $telegramFirstName,
        ?string $telegramLastName,
        ?string $telegramUsername = null,
    ): array {
        $telegramDisplayName = $this->joinNonEmpty([
            $telegramFirstName,
            $telegramLastName,
        ]);

        if ($this->isBlank($expectedName)) {
            return $this->noDataResult(
                expectedName: $expectedName,
                telegramName: $telegramDisplayName !== '' ? $telegramDisplayName : null,
                telegramUsername: $telegramUsername,
                reason: 'Driver name is missing.',
            );
        }

        if (
            $this->isBlank($telegramDisplayName)
            && $this->isBlank($telegramUsername)
        ) {
            return $this->noDataResult(
                expectedName: $expectedName,
                telegramName: null,
                telegramUsername: null,
                reason: 'Telegram name and username are both missing.',
            );
        }

        $driverTokens = $this->driverTokens($expectedName);

        $telegramNameTokens = $this->orderedUnique([
            ...$this->namedTokens($telegramFirstName, 'telegram_first_name'),
            ...$this->namedTokens($telegramLastName, 'telegram_last_name'),
        ], fn (array $t): string => $t['token']);

        $usernameNormalized = $this->normalizeUsername($telegramUsername);
        $usernameTokens = $this->usernameTokens($usernameNormalized);

        $telegramTokens = $this->orderedUnique(
            [...$telegramNameTokens, ...$usernameTokens],
            fn (array $t): string => $t['token'] . '|' . $t['source'],
        );

        if ($driverTokens === []) {
            return $this->noDataResult(
                expectedName: $expectedName,
                telegramName: $telegramDisplayName !== '' ? $telegramDisplayName : null,
                telegramUsername: $telegramUsername,
                reason: 'Driver name has no comparable tokens.',
            );
        }

        /*
         * The Telegram side had a non-blank raw value (checked above) but
         * it carried no identity information at all (e.g. an emoji-only
         * profile name). This is not "missing data" -- Telegram did
         * answer, it is simply unrelated to any name -- so it must fall
         * through to the normal scoring path and come out as a genuine
         * zero-score "no_match", not "no_data".
         */

        $tokenMatches = $this->matchDriverTokens(
            driverTokens: $driverTokens,
            telegramTokens: $telegramTokens,
            usernameCompact: $usernameNormalized,
        );

        $decision = $this->decide(
            driverTokenCount: count($driverTokens),
            tokenMatches: $tokenMatches,
        );

        return $this->buildResult(
            expectedName: $expectedName,
            telegramDisplayName: $telegramDisplayName,
            telegramUsername: $telegramUsername,
            driverTokens: $driverTokens,
            telegramTokens: $telegramTokens,
            tokenMatches: $tokenMatches,
            decision: $decision,
        );
    }

    /* ---------------------------------------------------------------------
     | Tokenization
     |--------------------------------------------------------------------- */

    /**
     * @return list<string> normalized, meaningful driver name tokens.
     */
    private function driverTokens(string $expectedName): array
    {
        $tokens = [];

        foreach ($this->tokens($expectedName) as $token) {
            if ($this->isPatronymicMarker($token)) {
                continue;
            }

            if ($this->isWeakStandaloneToken($token)) {
                continue;
            }

            $tokens[] = $token;
        }

        return $this->orderedUnique($tokens, static fn (string $t): string => $t);
    }

    /**
     * @return list<array{token: string, source: string}>
     */
    private function namedTokens(?string $value, string $source): array
    {
        $result = [];

        foreach ($this->tokens((string) $value) as $token) {
            $result[] = ['token' => $token, 'source' => $source];
        }

        return $result;
    }

    /**
     * Splits a Telegram username into sub-tokens on non-alphanumeric
     * boundaries and letter/digit transitions, dropping purely numeric
     * fragments (e.g. "ikramjon_dev92" -> ["ikramjon", "dev"]).
     *
     * @return list<array{token: string, source: string}>
     */
    private function usernameTokens(string $usernameNormalized): array
    {
        if ($usernameNormalized === '') {
            return [];
        }

        $parts = preg_split('/(?<=[a-z])(?=[0-9])|(?=[a-z])(?<=[0-9])/', $usernameNormalized) ?: [$usernameNormalized];

        $result = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || ctype_digit($part)) {
                continue;
            }

            if ($this->length($part) < 2) {
                continue;
            }

            $result[] = ['token' => $part, 'source' => 'username'];
        }

        return $result;
    }

    private function tokens(string $name): array
    {
        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $token) {
            $token = trim((string) $token);
            if ($token === '' || $this->length($token) < 2) {
                continue;
            }

            $tokens[] = $token;
        }

        return $this->orderedUnique($tokens, static fn (string $t): string => $t);
    }

    /* ---------------------------------------------------------------------
     | Token-pair comparison
     |--------------------------------------------------------------------- */

    /**
     * Compares one driver token against one Telegram token and returns an
     * explainable, bounded similarity score plus the tier that produced it.
     *
     * @return array{score: float, kind: string, reason: string}
     */
    private function compareTokens(string $driverToken, string $telegramToken): array
    {
        if ($driverToken === $telegramToken) {
            return [
                'score' => self::SCORE_EXACT,
                'kind' => 'exact',
                'reason' => 'Exact match',
            ];
        }

        $driverCanonical = $this->canonicalForm($driverToken);
        $telegramCanonical = $this->canonicalForm($telegramToken);

        if ($driverCanonical === '' || $telegramCanonical === '') {
            return ['score' => 0.0, 'kind' => 'none', 'reason' => 'No match'];
        }

        if ($driverCanonical === $telegramCanonical) {
            return [
                'score' => self::SCORE_TRANSLITERATION_VARIANT,
                'kind' => 'transliteration_variant',
                'reason' => 'Transliteration variant',
            ];
        }

        $typo = $this->typoSimilarity($driverCanonical, $telegramCanonical);
        $partial = $this->partialNameSimilarity($driverCanonical, $telegramCanonical);

        if ($typo['score'] >= $partial['score']) {
            return $typo['score'] > 0.0 ? $typo : ['score' => 0.0, 'kind' => 'none', 'reason' => 'No match'];
        }

        return $partial;
    }

    /**
     * Bounded edit-distance ("typo") similarity.
     *
     * Only accepted when the edit distance is small *relative to the
     * token length* -- this is what keeps two long, unrelated names
     * (e.g. NAMANJANOVICH vs SARVENAZ) at zero instead of a meaningless
     * ~25% Levenshtein-derived score.
     *
     * @return array{score: float, kind: string, reason: string}
     */
    private function typoSimilarity(string $a, string $b): array
    {
        $maxLength = max(strlen($a), strlen($b));
        if ($maxLength === 0) {
            return ['score' => 0.0, 'kind' => 'none', 'reason' => 'No match'];
        }

        $distance = levenshtein($a, $b);
        $allowed = max(1, intdiv($maxLength, 4));

        if ($distance > $allowed) {
            return ['score' => 0.0, 'kind' => 'none', 'reason' => 'No match'];
        }

        $ratioScore = 100.0 * (1.0 - ($distance / $maxLength));

        /*
         * A single extra/missing/substituted character on a reasonably
         * long token is a classic human spelling variation
         * (BEKHZOD/BEKZOD, IKRAMZHON/IKRAMJON) -- it should not be scored
         * much lower than an exact match.
         */
        if ($maxLength >= 5 && $distance <= 1) {
            $ratioScore = max(90.0, $ratioScore);
        }

        return [
            'score' => round(min(99.0, $ratioScore), 2),
            'kind' => 'minor_spelling_variant',
            'reason' => 'Minor spelling variation',
        ];
    }

    /**
     * Length-penalized substring ("nickname / partial name") similarity.
     *
     * Handles genuine partial-name usage (ABDUKABIR -> KABIR) while
     * keeping short/common fragments (KURBONALI -> ALI) weak on purpose.
     *
     * @return array{score: float, kind: string, reason: string}
     */
    private function partialNameSimilarity(string $a, string $b): array
    {
        $shortToken = strlen($a) <= strlen($b) ? $a : $b;
        $longToken = strlen($a) <= strlen($b) ? $b : $a;

        $shortLength = strlen($shortToken);
        $longLength = strlen($longToken);

        if (
            $shortLength < self::MIN_PARTIAL_TOKEN_LENGTH
            || $shortLength === $longLength
            || !str_contains($longToken, $shortToken)
        ) {
            return ['score' => 0.0, 'kind' => 'none', 'reason' => 'No match'];
        }

        $coverageRatio = $shortLength / $longLength;

        $score = 40.0 + ($shortLength * 6.0) + ($coverageRatio * 20.0);

        if ($shortLength < 4) {
            $score *= 0.6;
        }

        if (in_array($shortToken, self::COMMON_GENERIC_TOKENS, true)) {
            $score *= 0.7;
        }

        $score = min(88.0, $score);

        $reason = $shortLength >= 4
            ? 'Partial name / nickname match'
            : 'Short partial-name match';

        return [
            'score' => round(max(0.0, $score), 2),
            'kind' => 'partial_name_match',
            'reason' => $reason,
        ];
    }

    /**
     * Canonicalizes a token for comparison: folds well-known
     * transliteration variance (zh/j, kh/h, q/k, ...) and collapses
     * repeated vowels, without touching the token used for display.
     */
    private function canonicalForm(string $token): string
    {
        foreach (self::CANONICAL_REPLACEMENTS as $from => $to) {
            $token = str_replace($from, $to, $token);
        }

        return preg_replace('/([aeiou])\1+/', '$1', $token) ?? $token;
    }

    /* ---------------------------------------------------------------------
     | Per-driver-token best match
     |--------------------------------------------------------------------- */

    /**
     * For every driver token, finds the single best-matching Telegram
     * token (from the name fields, the split username, or the raw
     * username string for agglutinated usernames).
     *
     * @param list<string> $driverTokens
     * @param list<array{token: string, source: string}> $telegramTokens
     * @return list<array{
     *     driver_token: string,
     *     telegram_token: string,
     *     source: string,
     *     score: float,
     *     kind: string,
     *     reason: string,
     * }>
     */
    private function matchDriverTokens(
        array $driverTokens,
        array $telegramTokens,
        string $usernameCompact,
    ): array {
        $results = [];

        foreach ($driverTokens as $driverToken) {
            $best = null;

            foreach ($telegramTokens as $candidate) {
                $comparison = $this->compareTokens($driverToken, $candidate['token']);

                if ($best === null || $comparison['score'] > $best['score']) {
                    $best = [
                        'driver_token' => $driverToken,
                        'telegram_token' => $candidate['token'],
                        'source' => $candidate['source'],
                        'score' => $comparison['score'],
                        'kind' => $comparison['kind'],
                        'reason' => $comparison['reason'],
                    ];
                }
            }

            if (
                $usernameCompact !== ''
                && $this->length($driverToken) >= self::MIN_PARTIAL_TOKEN_LENGTH
            ) {
                $usernameCanonical = $this->canonicalForm($usernameCompact);
                $driverCanonical = $this->canonicalForm($driverToken);

                if (
                    $driverCanonical !== ''
                    && str_contains($usernameCanonical, $driverCanonical)
                ) {
                    $comparison = $this->partialNameSimilarity($driverCanonical, $usernameCanonical);

                    if (
                        $driverCanonical === $usernameCanonical
                    ) {
                        $comparison = ['score' => self::SCORE_TRANSLITERATION_VARIANT, 'kind' => 'transliteration_variant', 'reason' => 'Transliteration variant'];
                    }

                    if ($best === null || $comparison['score'] > $best['score']) {
                        $best = [
                            'driver_token' => $driverToken,
                            'telegram_token' => '@' . $usernameCompact,
                            'source' => 'username',
                            'score' => $comparison['score'],
                            'kind' => $comparison['kind'],
                            'reason' => $comparison['reason'],
                        ];
                    }
                }
            }

            if ($best !== null && $best['score'] > 0.0) {
                $results[] = $best;
            }
        }

        usort(
            $results,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score'],
        );

        return $results;
    }

    /* ---------------------------------------------------------------------
     | Decision engine
     |--------------------------------------------------------------------- */

    /**
     * @param list<array{driver_token: string, telegram_token: string, source: string, score: float, kind: string, reason: string}> $tokenMatches
     * @return array{score: float, matched: bool, decision: string, reason: string}
     */
    private function decide(
        int $driverTokenCount,
        array $tokenMatches,
    ): array {
        if ($tokenMatches === []) {
            return [
                'score' => 0.0,
                'matched' => false,
                'decision' => 'no_match',
                'reason' => 'No match',
            ];
        }

        $top1 = $tokenMatches[0];
        $top2 = $tokenMatches[1] ?? null;

        $top1Score = (float) $top1['score'];
        $top2Score = $top2 !== null ? (float) $top2['score'] : 0.0;

        /*
         * Fast path: the driver's full name (2+ tokens) is reproduced
         * almost exactly on Telegram, independent of field order.
         */
        $exactOrVariantCount = count(array_filter(
            $tokenMatches,
            static fn (array $m): bool => $m['score'] >= self::SCORE_TRANSLITERATION_VARIANT,
        ));

        $distinctTelegramTokens = count(array_unique(array_map(
            static fn (array $m): string => $m['telegram_token'],
            array_filter(
                $tokenMatches,
                static fn (array $m): bool => $m['score'] >= self::SCORE_TRANSLITERATION_VARIANT,
            ),
        )));

        if (
            $driverTokenCount >= 2
            && $exactOrVariantCount >= 2
            && $exactOrVariantCount === $driverTokenCount
            && $distinctTelegramTokens >= 2
        ) {
            return [
                'score' => self::SCORE_EXACT,
                'matched' => true,
                'decision' => 'exact_full_name',
                'reason' => 'All driver name tokens match exactly.',
            ];
        }

        /*
         * A second, independently matching token corroborates the best
         * match, but only ever nudges the score up -- it never dilutes a
         * single strong match (that is the bug being fixed here: a lone
         * very-strong first-name match must be allowed to stand on its
         * own instead of being capped by a missing/weak second field).
         */
        $bonus = $top2Score >= 40.0
            ? min(15.0, $top2Score * 0.2)
            : 0.0;

        $finalScore = round(min(100.0, $top1Score + $bonus), 2);

        $decision = $bonus > 0.0 ? 'corroborated_token_match' : 'single_token_match';

        return [
            'score' => $finalScore,
            'matched' => $finalScore >= self::CONFIRM_THRESHOLD,
            'decision' => $decision,
            'reason' => $top1['reason'],
        ];
    }

    /* ---------------------------------------------------------------------
     | Result building
     |--------------------------------------------------------------------- */

    private function buildResult(
        string $expectedName,
        string $telegramDisplayName,
        ?string $telegramUsername,
        array $driverTokens,
        array $telegramTokens,
        array $tokenMatches,
        array $decision,
    ): array {
        $score = (float) $decision['score'];
        $matched = (bool) $decision['matched'];
        $level = $this->resolveLevel($score);

        $matchedParts = array_map(
            fn (array $m): array => [
                'field' => strtoupper($m['driver_token']),
                'source' => $m['source'],
                'from' => strtoupper($m['telegram_token']),
                'to' => strtoupper($m['driver_token']),
                'score' => (float) $m['score'],
                'kind' => $m['kind'],
                'reason' => $m['reason'],
            ],
            array_slice($tokenMatches, 0, 8),
        );

        $reasons = $this->orderedUnique(
            array_merge(
                [(string) $decision['reason']],
                array_map(static fn (array $p): string => $p['reason'], $matchedParts),
            ),
            static fn (string $r): string => $r,
        );

        return [
            'matched' => $matched,
            'score' => round($score, 2),
            'level' => $level,
            'confidence' => $this->confidenceForLevel($level),
            'decision' => (string) $decision['decision'],
            'reason' => (string) $decision['reason'],

            'expected_name' => $expectedName,
            'telegram_name' => $telegramDisplayName,
            'telegram_username' => $telegramUsername,

            'normalized_expected' => $this->normalizeName($expectedName),
            'normalized_telegram' => $this->normalizeName($telegramDisplayName),
            'normalized_username' => $this->normalizeUsername($telegramUsername) ?: null,

            'driver_tokens' => array_map('strtoupper', $driverTokens),
            'telegram_tokens' => array_map(
                static fn (array $t): string => strtoupper($t['token']),
                $telegramTokens,
            ),

            'matched_parts' => $matchedParts,
            'reasons' => array_slice($reasons, 0, 6),
        ];
    }

    private function resolveLevel(float $score): string
    {
        if ($score <= 0.0) {
            return 'no_match';
        }

        if ($score >= self::LEVEL_VERY_STRONG) {
            return 'very_strong';
        }

        if ($score >= self::LEVEL_STRONG) {
            return 'strong';
        }

        if ($score >= self::LEVEL_LIKELY) {
            return 'likely';
        }

        if ($score >= self::LEVEL_POSSIBLE) {
            return 'possible';
        }

        return 'weak';
    }

    private function confidenceForLevel(string $level): string
    {
        return match ($level) {
            'very_strong', 'strong' => 'high',
            'likely' => 'medium',
            'possible', 'weak' => 'low',
            default => 'none',
        };
    }

    private function noDataResult(
        ?string $expectedName,
        ?string $telegramName,
        ?string $telegramUsername,
        string $reason,
    ): array {
        return [
            'matched' => false,
            'score' => 0.0,
            'level' => 'no_data',
            'confidence' => 'none',
            'decision' => 'no_data',
            'reason' => $reason,

            'expected_name' => $expectedName,
            'telegram_name' => $telegramName,
            'telegram_username' => $telegramUsername,

            'normalized_expected' => $expectedName !== null ? $this->normalizeName($expectedName) : null,
            'normalized_telegram' => $telegramName !== null ? $this->normalizeName($telegramName) : null,
            'normalized_username' => $telegramUsername !== null ? ($this->normalizeUsername($telegramUsername) ?: null) : null,

            'driver_tokens' => [],
            'telegram_tokens' => [],

            'matched_parts' => [],
            'reasons' => [$reason],
        ];
    }

    /* ---------------------------------------------------------------------
     | Normalization
     |--------------------------------------------------------------------- */

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $name = $this->decodeStyledCharacters($name);
        $name = preg_replace(
            '/[\x{200B}-\x{200D}\x{2060}\x{FE0E}-\x{FE0F}\x{FEFF}\x{00AD}]/u',
            '',
            $name,
        ) ?? $name;

        $name = $this->lower($name);
        $name = $this->stripDiacritics($name);
        $name = $this->transliterate($name);

        $name = str_replace(
            ["'", '’', '‘', '′', '`', 'ʻ', 'ʼ', 'ʹ', 'ʺ'],
            '',
            $name,
        );

        /* Everything except Latin/digits/space becomes a separator; this
         * is also what strips emoji and other decorative symbols. */
        $name = preg_replace('/[^a-z0-9\s]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function normalizeUsername(?string $username): string
    {
        if ($username === null || trim($username) === '') {
            return '';
        }

        return str_replace(' ', '', $this->normalizeName($username));
    }

    /* ---------------------------------------------------------------------
     | Cyrillic / diacritics / styled text
     |--------------------------------------------------------------------- */

    private function transliterate(string $value): string
    {
        return strtr($value, [
            'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd',
            'Е' => 'e', 'Ё' => 'yo', 'Ж' => 'j', 'З' => 'z', 'И' => 'i',
            'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
            'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't',
            'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch',
            'Ш' => 'sh', 'Щ' => 'sh', 'Ы' => 'i', 'Э' => 'e', 'Ю' => 'yu',
            'Я' => 'ya', 'Ъ' => '', 'Ь' => '',
            'Ў' => 'u', 'Қ' => 'q', 'Ғ' => 'g', 'Ҳ' => 'h',

            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'j', 'з' => 'z', 'и' => 'i',
            'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sh', 'ы' => 'i', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya', 'ъ' => '', 'ь' => '',
            'ў' => 'u', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h',
        ]);
    }

    private function stripDiacritics(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    }

    private function decodeStyledCharacters(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            return $value;
        }

        $result = '';
        foreach ($characters as $character) {
            $codePoint = $this->codePoint($character);
            if ($codePoint === null) {
                $result .= $character;
                continue;
            }

            $mapped = $this->mapStyledLatin($codePoint);
            $result .= $mapped ?? $character;
        }

        return $result;
    }

    private function mapStyledLatin(int $codePoint): ?string
    {
        /* Fullwidth Latin. */
        if ($codePoint >= 0xFF21 && $codePoint <= 0xFF3A) {
            return chr(0x41 + $codePoint - 0xFF21);
        }
        if ($codePoint >= 0xFF41 && $codePoint <= 0xFF5A) {
            return chr(0x61 + $codePoint - 0xFF41);
        }

        /* Circled / parenthesized Latin. */
        if ($codePoint >= 0x24B6 && $codePoint <= 0x24CF) {
            return chr(0x41 + $codePoint - 0x24B6);
        }
        if ($codePoint >= 0x24D0 && $codePoint <= 0x24E9) {
            return chr(0x61 + $codePoint - 0x24D0);
        }

        /* Mathematical Latin blocks. */
        $ranges = [
            [0x1D400, 0x1D419, 0x41],
            [0x1D41A, 0x1D433, 0x61],
            [0x1D434, 0x1D44D, 0x41],
            [0x1D44E, 0x1D467, 0x61],
            [0x1D468, 0x1D481, 0x41],
            [0x1D482, 0x1D49B, 0x61],
            [0x1D4D0, 0x1D4E9, 0x41],
            [0x1D4EA, 0x1D503, 0x61],
            [0x1D5A0, 0x1D5B9, 0x41],
            [0x1D5BA, 0x1D5D3, 0x61],
            [0x1D5D4, 0x1D5ED, 0x41],
            [0x1D5EE, 0x1D607, 0x61],
            [0x1D608, 0x1D621, 0x41],
            [0x1D622, 0x1D63B, 0x61],
        ];

        foreach ($ranges as [$start, $end, $base]) {
            if ($codePoint >= $start && $codePoint <= $end) {
                return chr($base + ($codePoint - $start));
            }
        }

        return null;
    }

    private function codePoint(string $character): ?int
    {
        $bytes = unpack('C*', $character);
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

        if (($first & 0xE0) === 0xC0 && isset($bytes[1])) {
            return (($first & 0x1F) << 6) | ($bytes[1] & 0x3F);
        }

        if (($first & 0xF0) === 0xE0 && isset($bytes[1], $bytes[2])) {
            return (($first & 0x0F) << 12)
                | (($bytes[1] & 0x3F) << 6)
                | ($bytes[2] & 0x3F);
        }

        if (($first & 0xF8) === 0xF0 && isset($bytes[1], $bytes[2], $bytes[3])) {
            return (($first & 0x07) << 18)
                | (($bytes[1] & 0x3F) << 12)
                | (($bytes[2] & 0x3F) << 6)
                | ($bytes[3] & 0x3F);
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     | Misc helpers
     |--------------------------------------------------------------------- */

    private function isWeakStandaloneToken(string $token): bool
    {
        return in_array($this->canonicalForm($token), self::COMMON_GENERIC_TOKENS, true)
            && $this->length($token) <= 3;
    }

    private function isPatronymicMarker(string $token): bool
    {
        return in_array($this->canonicalForm($token), self::PATRONYMIC_MARKERS, true);
    }

    /**
     * @template T
     * @param list<T> $values
     * @param callable(T): string $key
     * @return list<T>
     */
    private function orderedUnique(array $values, callable $key): array
    {
        $seen = [];
        $result = [];

        foreach ($values as $value) {
            $k = $key($value);
            if ($k === '' || isset($seen[$k])) {
                continue;
            }

            $seen[$k] = true;
            $result[] = $value;
        }

        return $result;
    }

    private function joinNonEmpty(array $values): string
    {
        return trim(implode(' ', array_values(array_filter(
            $values,
            static fn ($value): bool => $value !== null && trim((string) $value) !== '',
        ))));
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
