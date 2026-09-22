<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

use App\Application\Telegram\Services\NameMatching\Scoring\MatchDecision;

/**
 * The outcome of reading a Telegram display name as initials: a
 * ready-made decision plus the per-letter assignments behind it, so the
 * explanation an operator reads names which letter stood for which part
 * of the driver's name.
 */
final class InitialsEvidence
{
    /**
     * @param  list<TokenAssignment>  $assignments
     */
    public function __construct(
        public readonly MatchDecision $decision,
        public readonly array $assignments,
    ) {}
}
