<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Commonness\LengthBasedCommonnessRater;
use App\Application\Telegram\Services\NameMatching\Commonness\TokenCommonnessRater;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Tier 3: "nickname / partial name" evidence -- one token is a genuine
 * contiguous substring (prefix, suffix or infix) of the other, e.g.
 * ABDUKABIR -> KABIR (nickname), MUHAMMADALI -> ALI (suffix), or a
 * Telegram username containing the driver's first name.
 *
 * This is real evidence, but weaker and more dangerous than a spelling
 * variant: a short, common fragment recurs across many unrelated names
 * (ALI is a substring of dozens of names), so the score depends on:
 *
 *  - the *absolute* length of the shared fragment (longer = stronger),
 *  - its *coverage* of the longer token (higher ratio = stronger),
 *  - its *distinctiveness* (see {@see TokenCommonnessRater} -- a short,
 *    regionally common fragment is penalized on top of its length).
 *
 * The score is capped well below the exact/typo tiers' range so a
 * partial match alone can never look as convincing as real spelling
 * evidence.
 */
final class PartialNameComparator implements TokenComparator
{
    private const MIN_FRAGMENT_LENGTH = 3;

    private const SCORE_CAP = 88.0;

    public function __construct(
        private readonly TokenCommonnessRater $commonnessRater = new LengthBasedCommonnessRater,
    ) {}

    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        $a = $driverToken->canonical;
        $b = $telegramToken->canonical;

        if ($a === '' || $b === '' || strlen($a) === strlen($b)) {
            return ComparisonResult::none();
        }

        $short = strlen($a) < strlen($b) ? $a : $b;
        $long = strlen($a) < strlen($b) ? $b : $a;

        if (strlen($short) < self::MIN_FRAGMENT_LENGTH || ! str_contains($long, $short)) {
            return ComparisonResult::none();
        }

        $coverageRatio = strlen($short) / strlen($long);

        $base = 40.0 + (strlen($short) * 6.0) + ($coverageRatio * 20.0);

        $distinctiveness = $this->commonnessRater->distinctiveness($short);
        $penaltyFactor = min(1.0, 0.4 + (0.8 * $distinctiveness));

        $score = min(self::SCORE_CAP, $base * $penaltyFactor);

        $reason = strlen($short) >= 4
            ? 'Partial name / nickname match'
            : 'Short partial-name match';

        return ComparisonResult::make($score, 'partial_name_match', $reason);
    }
}
