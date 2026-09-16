<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

use App\Application\Telegram\Services\NameMatching\Comparison\ComparisonResult;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * One driver token matched to exactly one piece of Telegram evidence.
 */
final class TokenAssignment
{
    public function __construct(
        public readonly Token $driverToken,
        public readonly EvidenceToken $evidenceToken,
        public readonly ComparisonResult $comparison,
    ) {}

    public function score(): float
    {
        return $this->comparison->score;
    }
}
