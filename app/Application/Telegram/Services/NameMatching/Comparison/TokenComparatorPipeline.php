<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Runs every registered {@see TokenComparator} against a token pair and
 * keeps the strongest opinion. Comparators are independent of each
 * other and of ordering (ties fall back to registration order), so a
 * new comparator is added purely by appending it to the list passed
 * into the constructor -- nothing here needs to change.
 */
final class TokenComparatorPipeline
{
    /**
     * @param  list<TokenComparator>  $comparators
     */
    public function __construct(
        private readonly array $comparators,
    ) {}

    public static function default(): self
    {
        return new self([
            new CanonicalEqualityComparator,
            new EditDistanceComparator,
            new PartialNameComparator,
            new AffixTolerantComparator,
            new PhoneticKeyComparator,
        ]);
    }

    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        $best = ComparisonResult::none();

        foreach ($this->comparators as $comparator) {
            $result = $comparator->compare($driverToken, $telegramToken);

            if ($result->score > $best->score) {
                $best = $result;
            }
        }

        return $best;
    }
}
