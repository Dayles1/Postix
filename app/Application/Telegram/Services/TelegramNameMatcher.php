<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Application\Telegram\Services\NameMatching\Evidence\EvidenceSource;
use App\Application\Telegram\Services\NameMatching\Evidence\EvidenceToken;
use App\Application\Telegram\Services\NameMatching\Evidence\InitialsMatcher;
use App\Application\Telegram\Services\NameMatching\Evidence\TokenSetMatcher;
use App\Application\Telegram\Services\NameMatching\Explaining\MatchExplainer;
use App\Application\Telegram\Services\NameMatching\NameNormalizer;
use App\Application\Telegram\Services\NameMatching\NameTokenizer;
use App\Application\Telegram\Services\NameMatching\Roles\NameRoleClassifier;
use App\Application\Telegram\Services\NameMatching\Scoring\MatchDecision;
use App\Application\Telegram\Services\NameMatching\Scoring\MatchLevelClassifier;
use App\Application\Telegram\Services\NameMatching\Scoring\MatchScoreAggregator;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Telegram driver-name matcher -- thin orchestration facade.
 *
 * This is PROBABILISTIC NAME MATCHING, not identity verification. The
 * question it answers is:
 *
 *     "How likely is it that this Telegram profile (resolved by phone
 *      number) belongs to the driver we have on file?"
 *
 * The actual matching engine lives under
 * {@see NameMatching}, split by
 * responsibility so any single concern (a new Unicode style, a new
 * transliteration rule, a new comparison tier, a smarter commonness
 * model) can be extended without touching the rest:
 *
 *   NameMatching\NameNormalizer              case/diacritics/emoji/styled-Unicode/Cyrillic
 *   NameMatching\Support\OrthographicVariantFolder   canonical spelling-variant folding (zh<->j, kh<->h, ...)
 *   NameMatching\Support\NameAffixStripper    honorific affix stripping (Elyor <-> Elyorbek)
 *   NameMatching\Support\PhoneticKeyBuilder   vowel-axis folding (Adil <-> Odil)
 *   NameMatching\NameTokenizer                word/username splitting, patronymic filtering
 *   NameMatching\Roles\*                      surname / given name / patronymic of each token
 *   NameMatching\Comparison\*                 exact / transliteration / typo / partial-name /
 *                                             name-root / phonetic tiers
 *   NameMatching\Evidence\InitialsMatcher     display names that are only initials (K.B.A)
 *   NameMatching\Commonness\*                 short/common-fragment penalty (pluggable)
 *   NameMatching\Evidence\TokenSetMatcher     order-independent, one-to-one token assignment
 *   NameMatching\Scoring\*                    score aggregation + level/confidence classification
 *   NameMatching\Explaining\MatchExplainer    human-readable matched_parts/reasons
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
    private readonly TokenSetMatcher $tokenSetMatcher;

    public function __construct(
        private readonly NameNormalizer $normalizer = new NameNormalizer,
        private readonly NameTokenizer $tokenizer = new NameTokenizer,
        ?TokenSetMatcher $tokenSetMatcher = null,
        private readonly MatchScoreAggregator $aggregator = new MatchScoreAggregator,
        private readonly MatchLevelClassifier $classifier = new MatchLevelClassifier,
        private readonly MatchExplainer $explainer = new MatchExplainer,
        private readonly NameRoleClassifier $roleClassifier = new NameRoleClassifier,
        private readonly InitialsMatcher $initialsMatcher = new InitialsMatcher,
    ) {
        $this->tokenSetMatcher = $tokenSetMatcher ?? TokenSetMatcher::withDefaultComparators();
    }

    public function match(
        ?string $expectedName,
        ?string $telegramFirstName,
        ?string $telegramLastName,
        ?string $telegramUsername = null,
    ): array {
        $telegramDisplayName = $this->joinNonEmpty([$telegramFirstName, $telegramLastName]);

        if ($this->isBlank($expectedName)) {
            return $this->noDataResult(
                $expectedName,
                $telegramDisplayName !== '' ? $telegramDisplayName : null,
                $telegramUsername,
                'Driver name is missing.',
            );
        }

        if ($this->isBlank($telegramDisplayName) && $this->isBlank($telegramUsername)) {
            return $this->noDataResult(
                $expectedName,
                null,
                null,
                'Telegram name and username are both missing.',
            );
        }

        /*
         * Roles are assigned here, once, because every later stage wants
         * them: the scoring layer prices a matched given name above a
         * matched surname, and the initials reader needs the parts in
         * document order.
         */
        $driverTokens = $this->roleClassifier->classify(
            $this->tokenizer->tokenize((string) $expectedName),
            $this->normalizer->normalize((string) $expectedName),
        );

        if ($driverTokens === []) {
            return $this->noDataResult(
                $expectedName,
                $telegramDisplayName !== '' ? $telegramDisplayName : null,
                $telegramUsername,
                'Driver name has no comparable tokens.',
            );
        }

        $evidenceTokens = $this->evidenceTokens($telegramFirstName, $telegramLastName);
        $username = $this->tokenizer->tokenizeUsername($telegramUsername);

        foreach ($username['tokens'] as $token) {
            $evidenceTokens[] = new EvidenceToken($token, EvidenceSource::Username);
        }

        $assignments = $this->tokenSetMatcher->match($driverTokens, $evidenceTokens, $username['compact']);

        $decision = $this->aggregator->aggregate($assignments, count($driverTokens));

        /*
         * A display name that is only initials ("К.Б.А") carries no
         * tokens at all -- single letters are dropped long before the
         * comparison tiers ever run -- so it is read separately, as one
         * piece of whole-name evidence, and used when it says more than
         * the tokens managed to.
         */
        $initials = $this->initialsMatcher->match($driverTokens, $telegramDisplayName);

        if ($initials !== null && $initials->decision->score > $decision->score) {
            $decision = $initials->decision;
            $assignments = $initials->assignments;
        }

        return $this->buildResult(
            expectedName: (string) $expectedName,
            telegramDisplayName: $telegramDisplayName,
            telegramUsername: $telegramUsername,
            driverTokens: $driverTokens,
            evidenceTokens: $evidenceTokens,
            assignments: $assignments,
            decision: $decision,
        );
    }

    /**
     * @return list<EvidenceToken>
     */
    private function evidenceTokens(?string $telegramFirstName, ?string $telegramLastName): array
    {
        $tokens = [];

        foreach ($this->tokenizer->tokenize((string) $telegramFirstName) as $token) {
            $tokens[] = new EvidenceToken($token, EvidenceSource::FirstName);
        }

        foreach ($this->tokenizer->tokenize((string) $telegramLastName) as $token) {
            $tokens[] = new EvidenceToken($token, EvidenceSource::LastName);
        }

        return $tokens;
    }

    private function buildResult(
        string $expectedName,
        string $telegramDisplayName,
        ?string $telegramUsername,
        array $driverTokens,
        array $evidenceTokens,
        array $assignments,
        MatchDecision $decision,
    ): array {
        $level = $this->classifier->level($decision->score);
        $matchedParts = $this->explainer->matchedParts($assignments);

        return [
            'matched' => $this->classifier->isMatch($decision->score),
            'score' => round($decision->score, 2),
            'level' => $level,
            'confidence' => $this->classifier->confidence($level),
            'decision' => $decision->decision,
            'reason' => $decision->reason,

            'expected_name' => $expectedName,
            'telegram_name' => $telegramDisplayName,
            'telegram_username' => $telegramUsername,

            'normalized_expected' => $this->normalizer->normalize($expectedName),
            'normalized_telegram' => $this->normalizer->normalize($telegramDisplayName),
            'normalized_username' => $this->normalizer->normalizeUsername($telegramUsername) ?: null,

            'driver_tokens' => array_map(static fn (Token $t): string => $t->displayUpper(), $driverTokens),
            'telegram_tokens' => array_map(
                static fn (EvidenceToken $t): string => $t->token->displayUpper(),
                $evidenceTokens,
            ),

            'matched_parts' => $matchedParts,
            'reasons' => $this->explainer->reasons($decision, $matchedParts),
        ];
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

            'normalized_expected' => $expectedName !== null ? $this->normalizer->normalize($expectedName) : null,
            'normalized_telegram' => $telegramName !== null ? $this->normalizer->normalize($telegramName) : null,
            'normalized_username' => $telegramUsername !== null
                ? ($this->normalizer->normalizeUsername($telegramUsername) ?: null)
                : null,

            'driver_tokens' => [],
            'telegram_tokens' => [],

            'matched_parts' => [],
            'reasons' => [$reason],
        ];
    }

    private function joinNonEmpty(array $values): string
    {
        return trim(implode(' ', array_values(array_filter(
            $values,
            static fn ($value): bool => $value !== null && trim((string) $value) !== '',
        ))));
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
