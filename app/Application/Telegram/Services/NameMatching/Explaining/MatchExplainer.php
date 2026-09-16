<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Explaining;

use App\Application\Telegram\Services\NameMatching\Evidence\TokenAssignment;
use App\Application\Telegram\Services\NameMatching\Scoring\MatchDecision;

/**
 * Turns the internal assignment/decision objects into the plain-array
 * shape the rest of the application (Telegram reporter, exports,
 * `telegram_raw->name_match` JSON queries) already reads.
 */
final class MatchExplainer
{
    private const MAX_MATCHED_PARTS = 8;

    /**
     * @param  list<TokenAssignment>  $assignments
     * @return list<array{field: string, source: string, from: string, to: string, score: float, kind: string, reason: string}>
     */
    public function matchedParts(array $assignments): array
    {
        return array_map(
            static fn (TokenAssignment $a): array => [
                'field' => $a->driverToken->displayUpper(),
                'source' => $a->evidenceToken->source->value,
                'from' => $a->evidenceToken->token->displayUpper(),
                'to' => $a->driverToken->displayUpper(),
                'score' => $a->score(),
                'kind' => $a->comparison->kind,
                'reason' => $a->comparison->reason,
            ],
            array_slice($assignments, 0, self::MAX_MATCHED_PARTS),
        );
    }

    /**
     * @param  list<array{reason: string}>  $matchedParts
     * @return list<string>
     */
    public function reasons(MatchDecision $decision, array $matchedParts): array
    {
        $reasons = [$decision->reason];

        foreach ($matchedParts as $part) {
            $reasons[] = $part['reason'];
        }

        return array_slice(array_values(array_unique($reasons)), 0, 6);
    }
}
