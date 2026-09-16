<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * A single, independent way of deciding whether two tokens represent
 * the same identity fragment.
 *
 * This is the main extensibility point of the matching engine: a new
 * kind of comparison (a phonetic algorithm, a curated alias dictionary,
 * a keyboard-adjacency typo model, ...) is added by implementing this
 * interface and registering it in {@see TokenComparatorPipeline} --
 * nothing else in the engine needs to change.
 */
interface TokenComparator
{
    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult;
}
