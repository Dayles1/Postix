<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Scoring;

use App\Application\Telegram\Services\NameMatching\Evidence\TokenAssignment;
use App\Application\Telegram\Services\NameMatching\Evidence\TokenSetMatcher;
use App\Application\Telegram\Services\NameMatching\Roles\NameRole;

/**
 * Turns a set of per-driver-token assignments (see
 * {@see TokenSetMatcher})
 * into a single, explainable score.
 *
 * Two principles decide everything here.
 *
 * A single very strong match must be allowed to stand on its own. The
 * original field-role engine required a second corroborating field
 * almost everywhere, which silently capped obvious matches
 * (IKRAMZHON/IKRAMJON, BEKHZOD/BEKZOD) around 76-90 whenever only one
 * Telegram field was available. Here the best match is always the
 * primary signal; a second, independent match only ever adds a capped
 * bonus on top -- it can never dilute the first.
 *
 * And the part of the name that matched is priced, because the parts are
 * not interchangeable evidence. A Telegram profile is where somebody
 * writes what they want to be called, and what they write is their given
 * name: alone, in a diminutive, in Cyrillic, wrapped in emoji. The
 * surname turns up sometimes, the patronymic essentially never. So a
 * matched given name is the signal this whole check is looking for and
 * is worth full marks on its own, while a surname or a patronymic
 * carries a small discount -- families share a surname, and a profile
 * naming only the father is a thinner coincidence to rule out.
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

    /**
     * What a match on each part of the name is worth, relative to a
     * match on the given name.
     *
     * An unknown role is priced like a given name on purpose: the role
     * comes from a positional heuristic
     * ({@see \App\Application\Telegram\Services\NameMatching\Roles\NameRoleClassifier}),
     * and an unusually structured name must not be punished for
     * defeating it.
     *
     * @var array<string, float>
     */
    private const ROLE_WEIGHTS = [
        NameRole::GivenName->value => 1.0,
        NameRole::Unknown->value => 1.0,
        NameRole::Surname->value => 0.92,
        NameRole::Patronymic->value => 0.85,
    ];

    /**
     * @param  list<TokenAssignment>  $assignments
     */
    public function aggregate(array $assignments, int $driverTokenCount): MatchDecision
    {
        if ($assignments === []) {
            return new MatchDecision(0.0, 'no_match', 'No match');
        }

        if ($this->isFullNameReproduction($assignments, $driverTokenCount)) {
            return new MatchDecision(
                100.0,
                'exact_full_name',
                'All driver name tokens match exactly.',
            );
        }

        /*
         * Ranked by what each match is worth rather than by the raw
         * comparison score, so that an exact surname and an exact given
         * name -- identical on paper -- are led by the given name.
         */
        $ranked = $this->rankByWeightedScore($assignments);

        $top1 = $ranked[0];
        $top2 = $ranked[1] ?? null;

        $bonus = $this->corroborationBonus($top1, $top2);

        $finalScore = round(min(100.0, $this->weightedScore($top1) + $bonus), 2);

        $decision = $bonus > 0.0 ? 'corroborated_token_match' : 'single_token_match';

        return new MatchDecision($finalScore, $decision, $top1->comparison->reason);
    }

    /**
     * @param  list<TokenAssignment>  $assignments
     * @return list<TokenAssignment>
     */
    private function rankByWeightedScore(array $assignments): array
    {
        usort(
            $assignments,
            fn (TokenAssignment $a, TokenAssignment $b): int
                => $this->weightedScore($b) <=> $this->weightedScore($a),
        );

        return $assignments;
    }

    private function weightedScore(TokenAssignment $assignment): float
    {
        return $assignment->score()
            * (self::ROLE_WEIGHTS[$assignment->driverToken->role->value] ?? 1.0);
    }

    /**
     * Every part of the driver's name reproduced, exactly: there is
     * nothing left for a role discount to express, so the full-name
     * path is decided on the raw scores and returns the ceiling.
     *
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
        if ($top2 === null || $this->weightedScore($top2) < self::CORROBORATION_MIN_SCORE) {
            return 0.0;
        }

        $bonus = min(self::CORROBORATION_MAX_BONUS, $this->weightedScore($top2) * 0.2);

        if ($top1->evidenceToken->source === $top2->evidenceToken->source) {
            $bonus *= self::SAME_SOURCE_BONUS_DAMPENING;
        }

        return $bonus;
    }
}
