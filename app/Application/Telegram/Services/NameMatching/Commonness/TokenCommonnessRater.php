<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Commonness;

/**
 * Rates how "generic" a name fragment is -- a short, extremely common
 * fragment (ALI, BEK, MAX) is much weaker evidence of identity than a
 * long, distinctive one (IKRAMZHON), because it coincidentally recurs
 * across many unrelated people.
 *
 * This is its own interface (rather than a hardcoded rule buried in a
 * comparator) so the default length-based heuristic can later be
 * swapped for one backed by real name-frequency statistics without
 * touching any comparator.
 */
interface TokenCommonnessRater
{
    /**
     * @return float 0.0 (maximally generic, e.g. "ali") .. 1.0 (maximally
     *               distinctive, no penalty).
     */
    public function distinctiveness(string $canonicalToken): float;
}
