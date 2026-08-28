<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use Normalizer;

/**
 * Telegram driver-name matcher.
 *
 * The matcher is intentionally pragmatic rather than KYC-grade:
 * it tries to answer "is this Telegram account very likely the driver?"
 * while avoiding obvious false positives.
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

    private const SCORE_EXACT = 100.0;
    private const SCORE_VARIANT = 96.0;
    private const SCORE_NEAR = 90.0;
    private const SCORE_ORDERED = 78.0;
    private const SCORE_PHONETIC = 76.0;
    private const SCORE_FUZZY_STRONG = 88.0;
    private const SCORE_FUZZY_MIN = 45.0;

    private const CONFIRM_SCORE = 78.0;
    private const POSSIBLE_SCORE = 55.0;
    private const STRONG_NAME_SCORE = 88.0;
    private const STRONG_SURNAME_SCORE = 82.0;
    private const STRONG_USERNAME_SCORE = 92.0;
    private const NEAR_SURNAME_SCORE = 68.0;

    private const MIN_TOKEN_LENGTH = 2;
    private const MAX_MATCHES = 50;
    private const MAX_CANDIDATES_PER_FIELD = 12;

    /**
     * Decorative / generic words that should not identify a person.
     */
    private const WEAK_STANDALONE_TOKENS = [
        'aka', 'opa', 'uka', 'bro', 'sis', 'boss', 'king',
        'mr', 'mrs', 'miss', 'bek', 'beg', 'jon', 'jan',
        'hon', 'xon', 'boy',
    ];

    /**
     * Patronymic markers are useful structure but weak identity evidence.
     */
    private const PATRONYMIC_MARKERS = [
        'ogli', 'ogly', 'oglu', 'uly',
        'ovich', 'ovna', 'evich', 'yevich', 'evna', 'yevna',
    ];

    /**
     * Common name suffixes. They are ignored when comparing the core name.
     */
    private const NAME_SUFFIXES = [
        'jon', 'jan', 'bek', 'beg', 'xon', 'hon',
    ];

    /**
     * Canonical spelling replacements. These intentionally cover only
     * common Uzbek/Russian/Latin transliteration variation.
     */
    private const CANONICAL_REPLACEMENTS = [
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
                reason: 'missing_expected_name',
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
                reason: 'missing_telegram_name_and_username',
            );
        }

        $driver = $this->parseDriverName($expectedName);

        $telegram = [
            'first_name' => $this->normalizeName($telegramFirstName ?? ''),
            'last_name' => $this->normalizeName($telegramLastName ?? ''),
            'display_name' => $this->normalizeName($telegramDisplayName),
            'username' => $this->normalizeUsername($telegramUsername),
        ];

        $fieldResults = [
            'surname' => $this->matchSurname(
                driver: $driver,
                telegram: $telegram,
            ),
            'first_name' => $this->matchFirstName(
                driver: $driver,
                telegram: $telegram,
            ),
            'patronymic' => $this->matchPatronymic(
                driver: $driver,
                telegram: $telegram,
            ),
            'username' => $this->matchUsername(
                driver: $driver,
                username: $telegram['username'],
            ),
        ];

        /*
         * Exact normalized token-set/name matching gets top priority.
         * It is independent of Telegram first/last field ordering.
         */
        $exactDisplay = $this->exactDisplayDecision(
            driver: $driver,
            telegram: $telegram,
        );

        if ($exactDisplay !== null) {
            return $this->buildResult(
                expectedName: $expectedName,
                telegramDisplayName: $telegramDisplayName,
                telegramUsername: $telegramUsername,
                driver: $driver,
                telegram: $telegram,
                fieldResults: $fieldResults,
                decision: $exactDisplay,
            );
        }

        $identitySignals = $this->collectIdentitySignals($fieldResults);
        $decision = $this->decide(
            driver: $driver,
            telegram: $telegram,
            fieldResults: $fieldResults,
            identitySignals: $identitySignals,
        );

        return $this->buildResult(
            expectedName: $expectedName,
            telegramDisplayName: $telegramDisplayName,
            telegramUsername: $telegramUsername,
            driver: $driver,
            telegram: $telegram,
            fieldResults: $fieldResults,
            decision: $decision,
        );
    }

    /* ---------------------------------------------------------------------
     | Driver parsing
     |--------------------------------------------------------------------- */

    private function parseDriverName(?string $value): array
    {
        $normalized = $this->normalizeName($value ?? '');
        $tokens = $this->tokens($normalized);

        $surname = $tokens[0] ?? '';
        $firstName = $tokens[1] ?? '';
        $remaining = array_slice($tokens, 2);

        $patronymicTokens = [];
        foreach ($remaining as $token) {
            if ($this->isPatronymicMarker($token)) {
                continue;
            }
            $patronymicTokens[] = $token;
        }

        $patronymic = implode(' ', $patronymicTokens);

        $surnameParts = $this->splitNameSuffix($surname);
        $firstNameParts = $this->splitNameSuffix($firstName);

        return [
            'original' => $value,
            'normalized_full_name' => $normalized,
            'tokens' => $tokens,
            'surname' => $surname,
            'surname_core' => $surnameParts['core'],
            'surname_suffix' => $surnameParts['suffix'],
            'first_name' => $firstName,
            'first_name_core' => $firstNameParts['core'],
            'first_name_suffix' => $firstNameParts['suffix'],
            'patronymic' => $patronymic,
            'patronymic_tokens' => $patronymicTokens,
        ];
    }

    /* ---------------------------------------------------------------------
     | Field matching
     |--------------------------------------------------------------------- */

    private function matchSurname(array $driver, array $telegram): array
    {
        $expected = (string) ($driver['surname'] ?? '');
        if ($expected === '') {
            return $this->emptyFieldResult('surname');
        }

        $candidates = [];

        foreach ($this->orderedUnique([
            ...$this->tokens($telegram['last_name']),
            ...$this->tokens($telegram['first_name']),
            ...$this->tokens($telegram['display_name']),
        ]) as $token) {
            $comparison = $this->compareIdentityName($expected, $token);

            $multiplier = $this->sourceMultiplier(
                field: 'surname',
                source: $this->sourceForToken($token, $telegram),
            );

            $candidates[] = $this->fieldCandidate(
                field: 'surname',
                source: $this->sourceForToken($token, $telegram),
                expected: $expected,
                actual: $token,
                comparison: $comparison,
                strengthMultiplier: $multiplier,
            );
        }

        $usernameScore = $this->usernameContainsIdentity(
            username: $telegram['username'],
            identity: $expected,
        );

        if ($usernameScore > 0.0) {
            $candidates[] = $this->identityCandidate(
                field: 'surname',
                source: 'username',
                expected: $expected,
                actual: $telegram['username'],
                score: $usernameScore,
                reason: 'username_contains_surname',
            );
        }

        return $this->bestFieldResult('surname', $candidates);
    }

    private function matchFirstName(array $driver, array $telegram): array
    {
        $expected = (string) ($driver['first_name'] ?? '');
        if ($expected === '') {
            return $this->emptyFieldResult('first_name');
        }

        $candidates = [];

        foreach ($this->orderedUnique([
            ...$this->tokens($telegram['first_name']),
            ...$this->tokens($telegram['last_name']),
            ...$this->tokens($telegram['display_name']),
        ]) as $token) {
            $comparison = $this->compareIdentityName($expected, $token);

            $source = $this->sourceForToken($token, $telegram);
            $multiplier = $source === 'telegram_first_name' ? 1.00 : 0.92;

            $candidates[] = $this->fieldCandidate(
                field: 'first_name',
                source: $source,
                expected: $expected,
                actual: $token,
                comparison: $comparison,
                strengthMultiplier: $multiplier,
            );
        }

        $usernameScore = $this->usernameContainsIdentity(
            username: $telegram['username'],
            identity: $expected,
        );

        if ($usernameScore > 0.0) {
            $candidates[] = $this->identityCandidate(
                field: 'first_name',
                source: 'username',
                expected: $expected,
                actual: $telegram['username'],
                score: $usernameScore,
                reason: 'username_contains_first_name',
            );
        }

        return $this->bestFieldResult('first_name', $candidates);
    }

    private function matchPatronymic(array $driver, array $telegram): array
    {
        $expected = (string) ($driver['patronymic'] ?? '');
        if ($expected === '') {
            return $this->emptyFieldResult('patronymic');
        }

        $candidates = [];
        foreach ($this->orderedUnique([
            ...$this->tokens($telegram['first_name']),
            ...$this->tokens($telegram['last_name']),
        ]) as $token) {
            $comparison = $this->compareIdentityName($expected, $token);

            $candidates[] = $this->fieldCandidate(
                field: 'patronymic',
                source: $this->sourceForToken($token, $telegram),
                expected: $expected,
                actual: $token,
                comparison: $comparison,
                strengthMultiplier: 0.80,
            );
        }

        return $this->bestFieldResult('patronymic', $candidates);
    }

    private function matchUsername(array $driver, string $username): array
    {
        if ($username === '') {
            return $this->emptyFieldResult('username');
        }

        $candidates = [];

        foreach ([
            'surname' => $driver['surname'] ?? '',
            'first_name' => $driver['first_name'] ?? '',
        ] as $field => $identity) {
            if ($identity === '') {
                continue;
            }

            $score = $this->usernameContainsIdentity(
                username: $username,
                identity: $identity,
            );

            if ($score <= 0.0) {
                continue;
            }

            $candidates[] = $this->identityCandidate(
                field: 'username',
                source: 'username_' . $field,
                expected: $identity,
                actual: $username,
                score: $score,
                reason: 'username_contains_' . $field,
            );
        }

        return $this->bestFieldResult('username', $candidates);
    }

    /* ---------------------------------------------------------------------
     | Decision engine
     |--------------------------------------------------------------------- */

    private function decide(
        array $driver,
        array $telegram,
        array $fieldResults,
        array $identitySignals,
    ): array {
        $surname = $fieldResults['surname'];
        $first = $fieldResults['first_name'];
        $username = $fieldResults['username'];
        $patronymic = $fieldResults['patronymic'];

        $surnameScore = (float) ($surname['score'] ?? 0.0);
        $firstScore = (float) ($first['score'] ?? 0.0);
        $usernameScore = (float) ($username['score'] ?? 0.0);
        $patronymicScore = (float) ($patronymic['score'] ?? 0.0);

        $strongSurname = $this->isStrongSurnameResult($surname);
        $strongFirst = $this->isStrongFirstNameResult($first);
        $strongUsername = $this->isStrongUsernameResult($username);

        $firstExact = $this->isExactResult($first);
        $surnameExact = $this->isExactResult($surname);
        $usernameExactSurname = $this->isExactUsernameSurname($driver['surname'], $username);

        /*
         * 1. Exact / near-exact first + surname.
         */
        if ($strongFirst && $strongSurname) {
            return $this->matchedDecision(
                score: $this->weightedScore([
                    [$firstScore, 0.55],
                    [$surnameScore, 0.45],
                ]),
                decision: 'strong_name_match',
                reason: 'First name and surname strongly match.',
                confidence: 'high',
            );
        }

        /*
         * 2. Exact first + reasonably close surname.
         * This is the important "MAMADALIEV / MAMADALIYEV" case.
         */
        if (
            $firstExact
            && $surnameScore >= self::NEAR_SURNAME_SCORE
        ) {
            return $this->matchedDecision(
                score: $this->weightedScore([
                    [$firstScore, 0.60],
                    [$surnameScore, 0.40],
                ]),
                decision: 'exact_first_near_surname',
                reason: 'First name is exact and surname is compatible.',
                confidence: 'high',
            );
        }

        /*
         * 3. Exact first name can be enough when Telegram simply omitted
         * the surname. This is deliberately configurable through the score
         * constants and is much safer than arbitrary fuzzy matching.
         * Example: KORRIBANTS TIGRAN ... -> Telegram "Тигран Сергей".
         */
        if ($firstExact && $firstScore >= self::SCORE_EXACT) {
            return $this->matchedDecision(
                score: $firstScore,
                decision: 'exact_first_name_only',
                reason: 'Telegram first name exactly matches the driver first name.',
                confidence: 'medium',
            );
        }

        /*
         * 4. Strong surname + exact/strong username.
         */
        if ($strongSurname && $strongUsername) {
            return $this->matchedDecision(
                score: $this->weightedScore([
                    [$surnameScore, 0.60],
                    [$usernameScore, 0.40],
                ]),
                decision: 'strong_surname_username',
                reason: 'Surname and Telegram username strongly match.',
                confidence: 'high',
            );
        }

        /*
         * 5. Strong first name + strong username.
         */
        if ($strongFirst && $strongUsername) {
            return $this->matchedDecision(
                score: $this->weightedScore([
                    [$firstScore, 0.60],
                    [$usernameScore, 0.40],
                ]),
                decision: 'strong_first_name_username',
                reason: 'First name and Telegram username strongly match.',
                confidence: 'high',
            );
        }

        /*
         * 6. Exact surname from username + reasonable first name.
         */
        if (
            $usernameExactSurname
            && $firstScore >= 70.0
        ) {
            return $this->matchedDecision(
                score: $this->weightedScore([
                    [$usernameScore, 0.65],
                    [$firstScore, 0.35],
                ]),
                decision: 'surname_username_plus_first',
                reason: 'Surname is present in username and first name is compatible.',
                confidence: 'high',
            );
        }

        /*
         * 7. Two strong independent signals.
         */
        $strongSignals = array_values(array_filter(
            $identitySignals,
            static fn (array $signal): bool => (bool) ($signal['strong'] ?? false),
        ));

        if (count($strongSignals) >= 2) {
            $score = $this->identitySignalScore($strongSignals);
            if ($score >= self::CONFIRM_SCORE) {
                return $this->matchedDecision(
                    score: $score,
                    decision: 'multi_signal_match',
                    reason: 'Multiple strong identity signals matched.',
                    confidence: 'high',
                );
            }
        }

        /*
         * 8. Near first + near surname. Useful for spelling/transliteration
         * differences even when neither one is individually "strong".
         */
        if (
            $firstScore >= 72.0
            && $surnameScore >= self::NEAR_SURNAME_SCORE
        ) {
            $score = $this->weightedScore([
                [$firstScore, 0.58],
                [$surnameScore, 0.42],
            ]);

            if ($score >= self::CONFIRM_SCORE) {
                return $this->matchedDecision(
                    score: $score,
                    decision: 'near_name_pair',
                    reason: 'First name and surname are both sufficiently compatible.',
                    confidence: 'high',
                );
            }
        }

        /*
         * 9. Weak fuzzy evidence is not confirmation.
         */
        $best = max(
            $surnameScore,
            $firstScore,
            $usernameScore,
            $patronymicScore,
        );

        if ($best >= self::POSSIBLE_SCORE) {
            return [
                'matched' => false,
                'score' => round($best, 2),
                'decision' => 'possible_match',
                'reason' => 'Some name similarity exists, but identity evidence is insufficient.',
                'confidence' => 'low',
            ];
        }

        return [
            'matched' => false,
            'score' => round($best, 2),
            'decision' => 'no_match',
            'reason' => 'No sufficient identity evidence found.',
            'confidence' => 'none',
        ];
    }

    private function exactDisplayDecision(array $driver, array $telegram): ?array
    {
        $driverTokens = $this->identityTokenSet($driver['tokens']);
        $telegramTokens = $this->identityTokenSet(
            $this->tokens($telegram['display_name'])
        );

        if ($driverTokens === [] || $telegramTokens === []) {
            return null;
        }

        /* Full driver name can be omitted on Telegram, so only exact-match
         * the set when the number of Telegram identity tokens is at least 2.
         */
        if (
            count($telegramTokens) === count($driverTokens)
            && $this->sameTokenSet($driverTokens, $telegramTokens)
        ) {
            return $this->matchedDecision(
                score: self::SCORE_EXACT,
                decision: 'exact_full_name',
                reason: 'All normalized name tokens match exactly.',
                confidence: 'very_high',
            );
        }

        return null;
    }

    private function matchedDecision(
        float $score,
        string $decision,
        string $reason,
        string $confidence,
    ): array {
        return [
            'matched' => true,
            'score' => round(min(100.0, max(0.0, $score)), 2),
            'decision' => $decision,
            'reason' => $reason,
            'confidence' => $confidence,
        ];
    }

    /* ---------------------------------------------------------------------
     | Comparison
     |--------------------------------------------------------------------- */

    private function compareIdentityName(string $expected, string $actual): array
    {
        $expectedParts = $this->splitNameSuffix($expected);
        $actualParts = $this->splitNameSuffix($actual);

        $expectedCore = $expectedParts['core'];
        $actualCore = $actualParts['core'];

        if ($expectedCore === '' || $actualCore === '') {
            return $this->comparisonResult(0.0, 'empty_core', false, $expectedCore, $actualCore);
        }

        if ($expectedCore === $actualCore) {
            return $this->comparisonResult(
                self::SCORE_EXACT,
                'exact_core',
                true,
                $expectedCore,
                $actualCore,
            ) + [
                'suffix_compatible' => $this->suffixCompatible(
                    $expectedParts['suffix'],
                    $actualParts['suffix'],
                ),
            ];
        }

        /* Variant canonicalization often turns E/YE, H/X, Q/K etc. into
         * the same identity form. */
        $expectedVariant = $this->variantForm($expectedCore);
        $actualVariant = $this->variantForm($actualCore);

        if ($expectedVariant !== '' && $expectedVariant === $actualVariant) {
            return $this->comparisonResult(
                self::SCORE_VARIANT,
                'orthographic_variant',
                true,
                $expectedCore,
                $actualCore,
            );
        }

        /* One string inside another can represent a suffix/short-name form,
         * but never let 1-character or decorative pieces qualify. */
        if (
            $this->isMeaningfulLength($expectedCore)
            && $this->isMeaningfulLength($actualCore)
            && (
                str_contains($expectedCore, $actualCore)
                || str_contains($actualCore, $expectedCore)
            )
        ) {
            $shorter = min(
                strlen($expectedCore),
                strlen($actualCore),
            );

            $longer = max(
                strlen($expectedCore),
                strlen($actualCore),
            );

            $ratio = $shorter / $longer;

            /*
             * Very short words are dangerous. ALI inside VALI is not a
             * strong surname match. Real longer names may use ordered
             * containment as useful secondary evidence.
             */
            if ($shorter <= 4) {
                $score = round(45.0 + (20.0 * $ratio), 2);
            } else {
                $score = round(68.0 + (12.0 * $ratio), 2);
            }

            return $this->comparisonResult(
                $score,
                'ordered_contains',
                false,
                $expectedCore,
                $actualCore,
            );
        }

        $expectedPhonetic = $this->phoneticForm($expectedCore);
        $actualPhonetic = $this->phoneticForm($actualCore);

        if ($expectedPhonetic !== '' && $expectedPhonetic === $actualPhonetic) {
            return $this->comparisonResult(
                self::SCORE_PHONETIC,
                'phonetic_equal',
                false,
                $expectedCore,
                $actualCore,
            );
        }

        $fuzzy = $this->stringSimilarity($expectedCore, $actualCore);

        return $this->comparisonResult(
            $fuzzy,
            'fuzzy',
            false,
            $expectedCore,
            $actualCore,
        );
    }

    private function comparisonResult(
        float $score,
        string $reason,
        bool $coreExact,
        string $expectedCore,
        string $actualCore,
    ): array {
        return [
            'score' => round($score, 2),
            'reason' => $reason,
            'core_exact' => $coreExact,
            'expected_core' => $expectedCore,
            'actual_core' => $actualCore,
        ];
    }

    private function stringSimilarity(string $expected, string $actual): float
    {
        if ($expected === $actual) {
            return 100.0;
        }

        if ($expected === '' || $actual === '') {
            return 0.0;
        }

        /* Canonical variants get a strong score before generic fuzzy logic. */
        $expectedVariant = $this->variantForm($expected);
        $actualVariant = $this->variantForm($actual);
        if ($expectedVariant !== '' && $expectedVariant === $actualVariant) {
            return self::SCORE_VARIANT;
        }

        similar_text($expected, $actual, $similarTextPercent);

        $maxLength = max(strlen($expected), strlen($actual));
        $distance = levenshtein($expected, $actual);
        $levenshteinScore = $maxLength > 0
            ? max(0.0, 100.0 * (1.0 - ($distance / $maxLength)))
            : 0.0;

        $prefixScore = $this->commonPrefixScore($expected, $actual);
        $lengthRatio = min(strlen($expected), strlen($actual)) / $maxLength;

        /* A single extra/missing character should not destroy a name match. */
        if ($maxLength >= 5 && $distance <= 1) {
            return round(max(90.0, $levenshteinScore), 2);
        }

        $score = (
            ((float) $similarTextPercent * 0.40)
            + ($levenshteinScore * 0.45)
            + ($prefixScore * 0.10)
            + ($lengthRatio * 100.0 * 0.05)
        );

        /* Hard safety floor against tiny arbitrary overlaps. */
        if (
            $lengthRatio < 0.55
            && $score > 70.0
        ) {
            $score = 69.0;
        }

        return round(min(99.0, max(0.0, $score)), 2);
    }

    private function commonPrefixScore(string $a, string $b): float
    {
        $length = min(strlen($a), strlen($b));
        if ($length === 0) {
            return 0.0;
        }

        $prefix = 0;
        while ($prefix < $length && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        return round(($prefix / max(strlen($a), strlen($b))) * 100.0, 2);
    }

    private function phoneticForm(string $token): string
    {
        $token = $this->variantForm($token);

        return strtr($token, [
            'ph' => 'f',
            'th' => 't',
            'ck' => 'k',
            'qu' => 'k',
            'oo' => 'o',
            'ee' => 'e',
            'aa' => 'a',
            'ii' => 'i',
            'uu' => 'u',
            'zh' => 'j',
            'sh' => 's',
            'ch' => 'c',
        ]);
    }

    private function variantForm(string $value): string
    {
        $value = $this->compactToken($value);

        foreach (self::CANONICAL_REPLACEMENTS as $from => $to) {
            $value = str_replace($from, $to, $value);
        }

        /* Some spellings differ only by repeated vowel spelling. */
        $value = preg_replace('/([aeiou])\1+/', '$1', $value) ?? $value;

        return $value;
    }

    /* ---------------------------------------------------------------------
     | Username
     |--------------------------------------------------------------------- */

    private function usernameContainsIdentity(string $username, string $identity): float
    {
        if ($username === '' || $identity === '') {
            return 0.0;
        }

        $username = $this->normalizeUsername($username);
        $identityParts = $this->splitNameSuffix($identity);
        $core = $this->variantForm($identityParts['core']);

        if ($core === '' || !$this->isMeaningfulLength($core)) {
            return 0.0;
        }

        $usernameVariant = $this->variantForm($username);

        if (str_contains($usernameVariant, $core)) {
            return self::SCORE_EXACT;
        }

        /* Username may contain separators that have already been removed;
         * generic fuzzy matching is deliberately not used here. */
        return 0.0;
    }

    /* ---------------------------------------------------------------------
     | Candidate/result helpers
     |--------------------------------------------------------------------- */

    private function fieldCandidate(
        string $field,
        string $source,
        string $expected,
        string $actual,
        array $comparison,
        float $strengthMultiplier,
    ): array {
        $rawScore = (float) ($comparison['score'] ?? 0.0);
        $score = round($rawScore * $strengthMultiplier, 2);

        $identity = $this->isIdentityComparison(
            comparison: $comparison,
            actual: $actual,
        );

        $strong = $this->isStrongComparison(
            comparison: $comparison,
            score: $score,
            field: $field,
        );

        return [
            'field' => $field,
            'source' => $source,
            'expected' => $expected,
            'actual' => $actual,
            'score' => $score,
            'raw_score' => $rawScore,
            'reason' => $comparison['reason'] ?? 'unknown',
            'identity' => $identity,
            'strong' => $strong,
            'core_exact' => (bool) ($comparison['core_exact'] ?? false),
            'expected_core' => $comparison['expected_core'] ?? null,
            'actual_core' => $comparison['actual_core'] ?? null,
        ];
    }

    private function identityCandidate(
        string $field,
        string $source,
        string $expected,
        string $actual,
        float $score,
        string $reason,
    ): array {
        return [
            'field' => $field,
            'source' => $source,
            'expected' => $expected,
            'actual' => $actual,
            'score' => round($score, 2),
            'raw_score' => round($score, 2),
            'reason' => $reason,
            'identity' => $score >= self::SCORE_EXACT,
            'strong' => $score >= self::STRONG_USERNAME_SCORE,
            'core_exact' => $score >= self::SCORE_EXACT,
        ];
    }

    private function bestFieldResult(string $field, array $candidates): array
    {
        $candidates = $this->deduplicateCandidates($candidates);

        if ($candidates === []) {
            return $this->emptyFieldResult($field);
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int =>
                (($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0)),
        );

        $best = $candidates[0];

        return [
            ...$best,
            'field' => $field,
            'candidates' => array_slice($candidates, 0, self::MAX_CANDIDATES_PER_FIELD),
        ];
    }

    private function deduplicateCandidates(array $candidates): array
    {
        $unique = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $key = implode('|', [
                $candidate['field'] ?? '',
                $candidate['source'] ?? '',
                $this->normalizeName((string) ($candidate['expected'] ?? '')),
                $this->normalizeName((string) ($candidate['actual'] ?? '')),
            ]);

            if (!isset($unique[$key]) ||
                ($candidate['score'] ?? 0.0) > ($unique[$key]['score'] ?? 0.0)) {
                $unique[$key] = $candidate;
            }
        }

        return array_values($unique);
    }

    private function emptyFieldResult(string $field): array
    {
        return [
            'field' => $field,
            'source' => null,
            'expected' => null,
            'actual' => null,
            'score' => 0.0,
            'raw_score' => 0.0,
            'reason' => 'no_data',
            'identity' => false,
            'strong' => false,
            'core_exact' => false,
            'candidates' => [],
        ];
    }

    private function collectIdentitySignals(array $fieldResults): array
    {
        $signals = [];

        foreach (['surname', 'first_name', 'username'] as $field) {
            $result = $fieldResults[$field] ?? null;
            if (!is_array($result) || !($result['identity'] ?? false)) {
                continue;
            }

            $signals[] = [
                'field' => $field,
                'source' => $result['source'] ?? null,
                'score' => (float) ($result['score'] ?? 0.0),
                'strong' => (bool) ($result['strong'] ?? false),
                'actual' => $result['actual'] ?? null,
                'expected' => $result['expected'] ?? null,
                'reason' => $result['reason'] ?? null,
            ];
        }

        return $signals;
    }

    private function identitySignalScore(array $signals): float
    {
        if ($signals === []) {
            return 0.0;
        }

        usort(
            $signals,
            static fn (array $a, array $b): int =>
                (($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0)),
        );

        if (count($signals) === 1) {
            return (float) ($signals[0]['score'] ?? 0.0);
        }

        return $this->weightedScore([
            [(float) ($signals[0]['score'] ?? 0.0), 0.60],
            [(float) ($signals[1]['score'] ?? 0.0), 0.40],
        ]);
    }

    private function flattenFieldMatches(array $fieldResults): array
    {
        $matches = [];
        $seen = [];

        foreach ($fieldResults as $field => $result) {
            if (!is_array($result)) {
                continue;
            }

            $pool = [];
            if (
                array_key_exists('expected', $result)
                && array_key_exists('actual', $result)
                && $result['expected'] !== null
                && $result['actual'] !== null
            ) {
                $pool[] = $result;
            }

            foreach (($result['candidates'] ?? []) as $candidate) {
                if (is_array($candidate)) {
                    $pool[] = $candidate;
                }
            }

            foreach ($pool as $match) {
                $key = implode('|', [
                    $field,
                    $match['source'] ?? '',
                    $this->normalizeName((string) ($match['expected'] ?? '')),
                    $this->normalizeName((string) ($match['actual'] ?? '')),
                ]);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $match['field'] = $field;
                $matches[] = $match;
            }
        }

        usort(
            $matches,
            static fn (array $a, array $b): int =>
                (($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0)),
        );

        return array_slice(array_values($matches), 0, self::MAX_MATCHES);
    }

    private function buildMatchedParts(array $driver, array $fieldResults, array $telegram): array
    {
        $result = [];

        foreach ([
            'surname' => 'surname',
            'first_name' => 'first_name',
        ] as $field => $label) {
            $fieldResult = $fieldResults[$field] ?? null;
            if (!is_array($fieldResult) || !($fieldResult['identity'] ?? false)) {
                continue;
            }

            $result[] = [
                'field' => $label,
                'source' => $fieldResult['source'] ?? null,
                'from' => $fieldResult['actual'] ?? null,
                'to' => strtoupper($this->formatIdentityForOutput((string) ($driver[$field] ?? ''))),
                'score' => (float) ($fieldResult['score'] ?? 0.0),
                'reason' => $fieldResult['reason'] ?? null,
            ];
        }

        $usernameResult = $fieldResults['username'] ?? null;
        if (
            is_array($usernameResult)
            && ($usernameResult['identity'] ?? false)
            && $telegram['username'] !== ''
        ) {
            $result[] = [
                'field' => 'username',
                'source' => $usernameResult['source'] ?? 'username',
                'from' => '@' . ltrim($telegram['username'], '@'),
                'to' => strtoupper($this->usernameDisplayTarget($driver)),
                'score' => (float) ($usernameResult['score'] ?? 0.0),
                'reason' => $usernameResult['reason'] ?? null,
            ];
        }

        return $result;
    }

    private function usernameDisplayTarget(array $driver): string
    {
        return implode('+', array_values(array_filter([
            $this->formatIdentityForOutput((string) ($driver['first_name'] ?? '')),
            $this->formatIdentityForOutput((string) ($driver['surname'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));
    }

    private function buildReasons(array $fieldResults, array $decision): array
    {
        $reasons = [];

        if (($decision['matched'] ?? false) === true) {
            $reasons[] = 'identity_match';
        }

        if (!empty($decision['decision'])) {
            $reasons[] = (string) $decision['decision'];
        }

        foreach (['surname', 'first_name', 'patronymic', 'username'] as $field) {
            $reason = $fieldResults[$field]['reason'] ?? null;
            if (is_string($reason) && $reason !== '' && $reason !== 'no_data') {
                $reasons[] = $reason;
            }
        }

        return array_values(array_unique($reasons));
    }

    /* ---------------------------------------------------------------------
     | Identity rules
     |--------------------------------------------------------------------- */

    private function isIdentityComparison(array $comparison, string $actual): bool
    {
        $score = (float) ($comparison['score'] ?? 0.0);

        if (!empty($comparison['core_exact'])) {
            return true;
        }

        if (
            ($comparison['reason'] ?? '') === 'ordered_contains'
            && strlen($this->compactToken($actual)) >= 5
            && !$this->isWeakStandaloneToken($actual)
        ) {
            return true;
        }

        if (
            $score >= self::SCORE_NEAR
            && !$this->isWeakStandaloneToken($actual)
            && $this->isMeaningfulLength($actual)
        ) {
            return true;
        }

        if (
            $score >= self::SCORE_NEAR
            && $this->isMeaningfulLength($actual)
        ) {
            return true;
        }

        return false;
    }

    private function isStrongComparison(array $comparison, float $score, string $field): bool
    {
        if (!empty($comparison['core_exact'])) {
            return true;
        }

        if (
            $field === 'surname'
            && $score >= self::STRONG_SURNAME_SCORE
        ) {
            return true;
        }

        if (
            $field === 'first_name'
            && $score >= self::STRONG_NAME_SCORE
        ) {
            return true;
        }

        return false;
    }

    private function isStrongSurnameResult(array $result): bool
    {
        return ($result['identity'] ?? false)
            && (float) ($result['score'] ?? 0.0) >= self::STRONG_SURNAME_SCORE;
    }

    private function isStrongFirstNameResult(array $result): bool
    {
        return ($result['identity'] ?? false)
            && (float) ($result['score'] ?? 0.0) >= self::STRONG_NAME_SCORE;
    }

    private function isStrongUsernameResult(array $result): bool
    {
        return ($result['identity'] ?? false)
            && (float) ($result['score'] ?? 0.0) >= self::STRONG_USERNAME_SCORE;
    }

    private function isExactResult(array $result): bool
    {
        return ($result['core_exact'] ?? false) === true
            && (float) ($result['score'] ?? 0.0) >= self::SCORE_EXACT;
    }

    private function isExactUsernameSurname(string $driverSurname, array $usernameResult): bool
    {
        if ($driverSurname === '') {
            return false;
        }

        return ($usernameResult['source'] ?? null) === 'username_surname'
            && (float) ($usernameResult['score'] ?? 0.0) >= self::SCORE_EXACT;
    }

    private function weightedScore(array $signals): float
    {
        $total = 0.0;
        $weight = 0.0;

        foreach ($signals as [$score, $signalWeight]) {
            $total += ((float) $score * (float) $signalWeight);
            $weight += (float) $signalWeight;
        }

        return $weight > 0.0
            ? round(min(100.0, $total / $weight), 2)
            : 0.0;
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

        /* Cyrillic/Latin orthographic equivalence. */
        foreach (self::CANONICAL_REPLACEMENTS as $from => $to) {
            $name = str_replace($from, $to, $name);
        }

        /* Everything except Latin/digits/space becomes a separator. */
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
            if ($token === '' || $this->length($token) < self::MIN_TOKEN_LENGTH) {
                continue;
            }

            $canonical = $this->variantForm($token);
            if (!$this->isMeaningfulLength($canonical)) {
                continue;
            }

            $tokens[] = $token;
        }

        return $this->orderedUnique($tokens);
    }

    private function identityTokenSet(array $tokens): array
    {
        $result = [];
        foreach ($tokens as $token) {
            $token = $this->variantForm((string) $token);
            if (!$this->isMeaningfulLength($token) || $this->isWeakStandaloneToken($token)) {
                continue;
            }
            $result[] = $token;
        }

        $result = array_values(array_unique($result));
        sort($result);
        return $result;
    }

    private function sameTokenSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);
        return $a === $b;
    }

    private function compactToken(string $token): string
    {
        $token = $this->normalizeName($token);
        return preg_replace('/\s+/', '', $token) ?? $token;
    }

    private function splitNameSuffix(string $value): array
    {
        $value = $this->variantForm($value);
        if ($value === '') {
            return ['core' => '', 'suffix' => null];
        }

        foreach (self::NAME_SUFFIXES as $suffix) {
            if (strlen($value) <= strlen($suffix) + 1) {
                continue;
            }

            if (str_ends_with($value, $suffix)) {
                return [
                    'core' => substr($value, 0, -strlen($suffix)),
                    'suffix' => $suffix,
                ];
            }
        }

        return ['core' => $value, 'suffix' => null];
    }

    private function suffixCompatible(?string $expectedSuffix, ?string $actualSuffix): bool
    {
        return $expectedSuffix === null
            || $actualSuffix === null
            || $expectedSuffix === $actualSuffix;
    }

    private function formatIdentityForOutput(string $value): string
    {
        $parts = $this->splitNameSuffix($value);
        if ($parts['core'] === '') {
            return '';
        }

        return $parts['suffix'] === null
            ? $parts['core']
            : $parts['core'] . '+(' . $parts['suffix'] . ')';
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

    private function sourceForToken(string $token, array $telegram): string
    {
        if (in_array($token, $this->tokens($telegram['first_name']), true)) {
            return 'telegram_first_name';
        }

        if (in_array($token, $this->tokens($telegram['last_name']), true)) {
            return 'telegram_last_name';
        }

        return 'telegram_display_name';
    }

    private function sourceMultiplier(string $field, string $source): float
    {
        return match ($field) {
            'surname' => $source === 'telegram_last_name' ? 1.00 : 0.92,
            default => 1.00,
        };
    }

    private function isWeakStandaloneToken(string $token): bool
    {
        return in_array($this->variantForm($token), self::WEAK_STANDALONE_TOKENS, true);
    }

    private function containsWeakNicknameSuffix(string $value): bool
    {
        foreach ($this->tokens($value) as $token) {
            if ($this->isWeakStandaloneToken($token)) {
                return true;
            }
        }

        return false;
    }

    private function isPatronymicMarker(string $token): bool
    {
        return in_array($this->variantForm($token), self::PATRONYMIC_MARKERS, true);
    }

    private function isMeaningfulLength(string $value): bool
    {
        return $this->length($this->variantForm($value)) >= self::MIN_TOKEN_LENGTH;
    }

    private function orderedUnique(array $values): array
    {
        $seen = [];
        $result = [];

        foreach ($values as $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
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

    private function noDataResult(
        ?string $expectedName,
        ?string $telegramName,
        ?string $telegramUsername,
        string $reason,
    ): array {
        return [
            'matched' => false,
            'score' => 0.0,
            'level' => 'none',
            'expected_name' => $expectedName,
            'telegram_name' => $telegramName,
            'telegram_username' => $telegramUsername,
            'normalized_expected' => $expectedName !== null ? $this->normalizeName($expectedName) : null,
            'normalized_telegram' => $telegramName !== null ? $this->normalizeName($telegramName) : null,
            'normalized_username' => $telegramUsername !== null ? $this->normalizeUsername($telegramUsername) : null,
            'driver' => [
                'surname' => null,
                'first_name' => null,
                'patronymic' => null,
                'surname_core' => null,
                'first_name_core' => null,
                'surname_suffix' => null,
                'first_name_suffix' => null,
            ],
            'field_results' => [],
            'identity_signals' => [],
            'matched_parts' => [],
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
            'reasons' => [$reason],
        ];
    }

    private function buildResult(
        string $expectedName,
        string $telegramDisplayName,
        ?string $telegramUsername,
        array $driver,
        array $telegram,
        array $fieldResults,
        array $decision,
    ): array {
        $matches = $this->flattenFieldMatches($fieldResults);
        $identitySignals = $this->collectIdentitySignals($fieldResults);
        $matchedParts = $this->buildMatchedParts($driver, $fieldResults, $telegram);
        $score = (float) ($decision['score'] ?? 0.0);
        $matched = (bool) ($decision['matched'] ?? false);
        $level = $this->resolveLevel(
            score: $score,
            matched: $matched,
            decision: (string) ($decision['decision'] ?? 'no_match'),
        );
        $reasons = $this->buildReasons($fieldResults, $decision);

        $identityMatches = array_values(array_filter(
            $matches,
            static fn (array $match): bool => (bool) ($match['identity'] ?? false),
        ));

        $usefulMatches = array_values(array_filter(
            $matches,
            static fn (array $match): bool => (float) ($match['score'] ?? 0.0) >= self::SCORE_FUZZY_MIN,
        ));

        $telegramTokens = $this->tokens($telegram['display_name']);

        return [
            'matched' => $matched,
            'score' => round($score, 2),
            'level' => $level,

            'expected_name' => $expectedName,
            'telegram_name' => $telegramDisplayName,
            'telegram_username' => $telegramUsername,

            'normalized_expected' => $driver['normalized_full_name'],
            'normalized_telegram' => $telegram['display_name'],
            'normalized_username' => $telegram['username'] !== '' ? $telegram['username'] : null,

            'driver' => [
                'surname' => $driver['surname'],
                'first_name' => $driver['first_name'],
                'patronymic' => $driver['patronymic'],
                'surname_core' => $driver['surname_core'],
                'first_name_core' => $driver['first_name_core'],
                'surname_suffix' => $driver['surname_suffix'],
                'first_name_suffix' => $driver['first_name_suffix'],
            ],

            'field_results' => $fieldResults,
            'identity_signals' => $identitySignals,
            'matched_parts' => $matchedParts,

            'matched_tokens' => array_slice($identityMatches, 0, self::MAX_MATCHES),
            'possible_tokens' => array_slice($matches, 0, self::MAX_MATCHES),
            'matched_token_count' => count($identityMatches),
            'useful_token_count' => count($usefulMatches),
            'expected_token_count' => count($driver['tokens']),
            'telegram_token_count' => count($telegramTokens),
            'match_ratio' => count($telegramTokens) > 0
                ? round(count($identityMatches) / count($telegramTokens), 4)
                : 0.0,
            'best_token_score' => $matches !== []
                ? round(max(array_map(
                    static fn (array $match): float => (float) ($match['score'] ?? 0.0),
                    $matches,
                )), 2)
                : 0.0,

            /* Compatibility fields. */
            'weighted_token_score' => round($score, 2),
            'full_name_similarity' => round($score, 2),
            'partial_name_bonus' => $this->calculatePartialBonus($fieldResults),
            'multi_token_bonus' => count($identitySignals) >= 2 ? 85.0 : 0.0,
            'identity_token_bonus' => $identitySignals !== []
                ? max(array_map(
                    static fn (array $signal): float => (float) ($signal['score'] ?? 0.0),
                    $identitySignals,
                ))
                : 0.0,

            'reasons' => $reasons,
            'decision' => $decision,
        ];
    }

    private function resolveLevel(float $score, bool $matched, string $decision): string
    {
        if ($matched) {
            if ($decision === 'exact_full_name') {
                return 'exact';
            }
            if ($score >= 95.0) {
                return 'very_high';
            }
            if ($score >= 85.0) {
                return 'high';
            }
            return 'confirmed';
        }

        if ($score >= 85.0) {
            return 'possible';
        }
        if ($score >= 70.0) {
            return 'weak';
        }
        if ($score >= 40.0) {
            return 'low';
        }

        return 'none';
    }

    private function calculatePartialBonus(array $fieldResults): float
    {
        $bonus = 0.0;

        foreach (['surname', 'first_name'] as $field) {
            $score = (float) ($fieldResults[$field]['score'] ?? 0.0);
            if ($score >= self::SCORE_EXACT) {
                $bonus += 10.0;
            } elseif ($score >= self::SCORE_ORDERED) {
                $bonus += 5.0;
            }
        }

        return min(20.0, $bonus);
    }
}

