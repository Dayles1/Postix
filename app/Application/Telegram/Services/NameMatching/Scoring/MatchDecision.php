<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Scoring;

/**
 * The raw output of {@see MatchScoreAggregator}: a score plus *how* it
 * was derived. Level/confidence/matched are deliberately not decided
 * here -- see {@see MatchLevelClassifier} -- so the score itself stays
 * a clean, reusable signal independent of any particular business
 * threshold.
 */
final class MatchDecision
{
    public function __construct(
        public readonly float $score,
        public readonly string $decision,
        public readonly string $reason,
    ) {}
}
