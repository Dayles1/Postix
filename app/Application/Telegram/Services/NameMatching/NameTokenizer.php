<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching;

/**
 * Splits a normalized name or username into meaningful {@see Token}s.
 *
 * Responsibilities kept deliberately narrow:
 *  - word splitting (whitespace + letter/digit boundaries, so
 *    "ikramjon1999" and "007bekzod" expose their letter part as its own
 *    token -- numbers are otherwise dropped, see item 18/20 of the spec);
 *  - dropping pure-digit tokens (birth years, decoration numbers carry
 *    no identity information);
 *  - dropping patronymic/lineage markers (ogli/ogly/oglu/uly/ugli/qizi/
 *    kizi/qizy) as *standalone* tokens -- they are structure, not
 *    identity, and essentially never appear on a Telegram profile.
 *
 * Everything else (transliteration, styled-text decoding, emoji/symbol
 * removal) already happened in {@see NameNormalizer} before this class
 * ever sees the string.
 */
final class NameTokenizer
{
    /**
     * Lineage/patronymic markers. Matched against the *display* token
     * because they are plain Latin words in every spelling variant that
     * matters here; no orthographic folding is needed to recognize them.
     */
    private const PATRONYMIC_MARKERS = [
        'ogli', 'ogly', 'oglu', 'uly', 'ugli',
        'qizi', 'kizi', 'qizy',
    ];

    public function __construct(
        private readonly NameNormalizer $normalizer = new NameNormalizer,
    ) {}

    /**
     * @return list<Token>
     */
    public function tokenize(string $rawName): array
    {
        $normalized = $this->normalizer->normalize($rawName);

        return $this->tokensFromNormalized($normalized);
    }

    /**
     * @return array{tokens: list<Token>, compact: string}
     */
    public function tokenizeUsername(?string $rawUsername): array
    {
        if ($rawUsername === null || trim($rawUsername) === '') {
            return ['tokens' => [], 'compact' => ''];
        }

        $username = ltrim(trim($rawUsername), '@');
        $normalized = $this->normalizer->normalize($username);

        return [
            'tokens' => $this->tokensFromNormalized($normalized),
            'compact' => $this->normalizer->normalizeUsername($username),
        ];
    }

    /**
     * @return list<Token>
     */
    private function tokensFromNormalized(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        $seen = [];

        foreach ($words as $word) {
            foreach ($this->splitLetterDigitBoundaries($word) as $fragment) {
                if ($fragment === '' || $this->length($fragment) < 2) {
                    continue;
                }

                if (ctype_digit($fragment)) {
                    continue;
                }

                if (in_array($fragment, self::PATRONYMIC_MARKERS, true)) {
                    continue;
                }

                if (isset($seen[$fragment])) {
                    continue;
                }

                $seen[$fragment] = true;
                $tokens[] = new Token($fragment);
            }
        }

        return $tokens;
    }

    /**
     * Splits "ikramjon1999" -> ["ikramjon", "1999"] and
     * "007bekzod" -> ["007", "bekzod"] on letter/digit transitions, so a
     * name glued to a number in a username never hides the name part.
     *
     * @return list<string>
     */
    private function splitLetterDigitBoundaries(string $word): array
    {
        $parts = preg_split('/(?<=[a-z])(?=[0-9])|(?<=[0-9])(?=[a-z])/', $word) ?: [$word];

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
