<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Scoring;

/**
 * Maps a raw score onto an explainable level/confidence, and provides
 * the engine's own default recommendation for the "matched" boolean.
 *
 * Deliberately kept separate from {@see MatchScoreAggregator}: the
 * score is a probability-like signal, `matched` is one particular
 * policy applied to it. A caller that wants a different business
 * threshold (e.g. requiring VERY_STRONG before auto-confirming a
 * high-risk driver) can read `score`/`level` directly and ignore
 * `matched` entirely -- nothing about the scoring itself is tied to
 * this threshold.
 */
final class MatchLevelClassifier
{
    /**
     * The engine's own default recommendation threshold for `matched`.
     * This is a policy default, not a scoring constant -- see the class
     * docblock.
     */
    public const DEFAULT_MATCH_THRESHOLD = 75.0;

    private const LEVEL_VERY_STRONG = 90.0;

    private const LEVEL_STRONG = 75.0;

    private const LEVEL_LIKELY = 60.0;

    private const LEVEL_POSSIBLE = 40.0;

    public function level(float $score): string
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

    public function confidence(string $level): string
    {
        return match ($level) {
            'very_strong', 'strong' => 'high',
            'likely' => 'medium',
            'possible', 'weak' => 'low',
            default => 'none',
        };
    }

    public function isMatch(float $score): bool
    {
        return $score >= self::DEFAULT_MATCH_THRESHOLD;
    }
}
