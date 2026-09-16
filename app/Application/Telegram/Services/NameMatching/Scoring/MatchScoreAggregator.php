<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Scoring;

use App\Application\Telegram\Services\NameMatching\Evidence\TokenAssignment;
use App\Application\Telegram\Services\NameMatching\Evidence\TokenSetMatcher;

/**
 * Turns a set of per-driver-token assignments (see
 * {@see TokenSetMatcher})
 * into a single, explainable score.
 *
 * Design principle: a single very strong match must be allowed to stand
 * on its own. The previous field-role based engine required a second
 * corroborating field almost everywhere, which silently capped obvious
 * matches (IKRAMZHON/IKRAMJON, BEKHZOD/BEKZOD) around 76-90 whenever
 * only one Telegram field was available. Here, the best match is always
 * the primary signal; a second, independent match only ever adds a
 * capped bonus on top -- it can never dilute the first.
 */
final class MatchScoreAggregator
{
    /**
     * A second match only corroborates when it is itself reasonably
     * convincing; below this it is too weak to be worth anything.
     */
    private const CORROBORATION_MIN_SCORE = 40.0;

    private const CORROBORATION_MAX_BONUS = 15.0;

    /**
     * A second match from a *different* evidence field (e.g. surname
     * from last_name while first name matched from first_name) is
     * independent corroboration. A second match from the *same* field
     * (e.g. two sub-tokens split out of one username) is really one
     * signal typed once, so it is worth less.
     */
    private const SAME_SOURCE_BONUS_DAMPENING = 0.7;

    private const EXACT_TIER_THRESHOLD = 96.0;

    public function aggregate(array $assignments, int $driverTokenCount): MatchDecision
    {
        if ($assignments === []) {
            return new MatchDecision(0.0, 'no_match', 'No match');
        }

        $top1 = $assignments[0];
        $top2 = $assignments[1] ?? null;

        if ($this->isFullNameReproduction($assignments, $driverTokenCount)) {
            return new MatchDecision(
                100.0,
                'exact_full_name',
                'All driver name tokens match exactly.',
            );
        }

        $bonus = $this->corroborationBonus($top1, $top2);

        $finalScore = round(min(100.0, $top1->score() + $bonus), 2);

        $decision = $bonus > 0.0 ? 'corroborated_token_match' : 'single_token_match';

        return new MatchDecision($finalScore, $decision, $top1->comparison->reason);
    }

    /**
     * @param  list<TokenAssignment>  $assignments
     */
    private function isFullNameReproduction(array $assignments, int $driverTokenCount): bool
    {
        if ($driverTokenCount < 2) {
            return false;
        }

        $strongAssignments = array_filter(
            $assignments,
            static fn (TokenAssignment $a): bool => $a->score() >= self::EXACT_TIER_THRESHOLD,
        );

        return count($strongAssignments) >= 2
            && count($strongAssignments) === $driverTokenCount;
    }

    private function corroborationBonus(TokenAssignment $top1, ?TokenAssignment $top2): float
    {
        if ($top2 === null || $top2->score() < self::CORROBORATION_MIN_SCORE) {
            return 0.0;
        }

        $bonus = min(self::CORROBORATION_MAX_BONUS, $top2->score() * 0.2);

        if ($top1->evidenceToken->source === $top2->evidenceToken->source) {
            $bonus *= self::SAME_SOURCE_BONUS_DAMPENING;
        }

        return $bonus;
    }
}
