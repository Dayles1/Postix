<?php

namespace App\Application\Telegram\Services;

class TelegramNameMatcher
{
    public function match(
        ?string $expectedName,
        ?string $telegramFirstName,
        ?string $telegramLastName,
    ): array {
        $telegramFullName = trim(
            implode(' ', array_filter([
                $telegramFirstName,
                $telegramLastName,
            ]))
        );

        if (! $expectedName || ! $telegramFullName) {
            return [
                'matched' => false,
                'score' => 0,
                'level' => 'no_data',
                'expected_name' => $expectedName,
                'telegram_name' => $telegramFullName ?: null,
                'matched_tokens' => [],
                'possible_tokens' => [],
            ];
        }

        $expectedTokens = $this->tokens($expectedName);
        $telegramTokens = $this->tokens($telegramFullName);

        if ($expectedTokens === [] || $telegramTokens === []) {
            return [
                'matched' => false,
                'score' => 0,
                'level' => 'no_data',
                'expected_name' => $expectedName,
                'telegram_name' => $telegramFullName,
                'matched_tokens' => [],
                'possible_tokens' => [],
            ];
        }

        /*
         * Exact normalized full name.
         */
        if (
            $this->normalizeName($expectedName)
            ===
            $this->normalizeName($telegramFullName)
        ) {
            return [
                'matched' => true,
                'score' => 100,
                'level' => 'exact',
                'expected_name' => $expectedName,
                'telegram_name' => $telegramFullName,
                'matched_tokens' => $expectedTokens,
                'possible_tokens' => $expectedTokens,
            ];
        }

        /*
         * Compare every expected token with the closest
         * Telegram token.
         */
        $matches = [];

        foreach ($expectedTokens as $expectedToken) {
            $best = null;

            foreach ($telegramTokens as $telegramToken) {
                $similarity = $this->tokenSimilarity(
                    $expectedToken,
                    $telegramToken
                );

                if ($best === null || $similarity > $best['score']) {
                    $best = [
                        'expected' => $expectedToken,
                        'actual' => $telegramToken,
                        'score' => $similarity,
                    ];
                }
            }

            if ($best) {
                $matches[] = $best;
            }
        }

        if ($matches === []) {
            return [
                'matched' => false,
                'score' => 0,
                'level' => 'none',
                'expected_name' => $expectedName,
                'telegram_name' => $telegramFullName,
                'matched_tokens' => [],
                'possible_tokens' => [],
            ];
        }

        /*
         * Sort strongest matches first.
         */
        usort(
            $matches,
            static fn (array $a, array $b) =>
                $b['score'] <=> $a['score']
        );

        /*
         * We don't need every token to match.
         *
         * Examples:
         *
         * ERGASHEV BAKHROM
         * BAKHROM
         *
         * is still useful.
         */
        $strongMatches = array_filter(
            $matches,
            static fn (array $match) =>
                $match['score'] >= 75
        );

        $strongScores = array_map(
            static fn (array $match) =>
                $match['score'],
            $strongMatches
        );

        $bestScore = $matches[0]['score'] ?? 0;

        $averageScore = $strongScores !== []
            ? array_sum($strongScores) / count($strongScores)
            : $bestScore;

        /*
         * Bonus for multiple matching name parts.
         */
        $multiMatchBonus = count($strongMatches) >= 2
            ? 10
            : 0;

        $finalScore = min(
            100,
            round($averageScore + $multiMatchBonus, 2)
        );

        /*
         * Confidence levels.
         */
        $level = match (true) {
            $finalScore >= 90 => 'very_high',
            $finalScore >= 80 => 'high',
            $finalScore >= 70 => 'medium',
            $finalScore >= 55 => 'low',
            default => 'very_low',
        };

        /*
         * For the first prototype:
         *
         * 80+ = automatically confirmed.
         *
         * 55-79 = not confirmed, but report as suspicious match.
         *
         * <55 = mismatch.
         */
        $matched = $finalScore >= 80;

        return [
            'matched' => $matched,
            'score' => $finalScore,
            'level' => $level,

            'expected_name' => $expectedName,
            'telegram_name' => $telegramFullName,

            'matched_tokens' => array_values(
                array_filter(
                    $matches,
                    static fn (array $match) =>
                        $match['score'] >= 75
                )
            ),

            'possible_tokens' => $matches,
        ];
    }

    private function tokens(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return [];
        }

        return array_values(
            array_unique(
                preg_split('/\s+/u', $normalized)
            )
        );
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        /*
         * Cyrillic → Latin.
         */
        $name = $this->transliterate($name);

        /*
         * Remove apostrophes and punctuation.
         */
        $name = str_replace(
            ["'", "’", "`", "ʻ", "ʼ", "-", "_"],
            '',
            $name
        );

        /*
         * Transliteration variants:
         *
         * Бахром
         * Bakhrom
         * Bahrom
         * Baxrom
         *
         * should become comparable.
         */
        $name = str_replace(
            [
                'kh',
                'x',
                'h',
            ],
            'h',
            $name
        );

        /*
         * Common alternatives.
         */
        $name = str_replace(
            [
                'q',
                'k',
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

        $name = preg_replace('/[^a-z0-9\s]/u', ' ', $name);

        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    private function transliterate(string $value): string
    {
        return strtr($value, [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
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
            'қ' => 'k',
            'ғ' => 'g',
            'ҳ' => 'h',
        ]);
    }

    private function tokenSimilarity(
        string $expected,
        string $actual,
    ): float {
        if ($expected === $actual) {
            return 100;
        }

        /*
         * One contains the other.
         */
        if (
            strlen($expected) >= 4
            && strlen($actual) >= 4
            && (
                str_contains($expected, $actual)
                || str_contains($actual, $expected)
            )
        ) {
            return 95;
        }

        similar_text(
            $expected,
            $actual,
            $percent
        );

        return round($percent, 2);
    }
}