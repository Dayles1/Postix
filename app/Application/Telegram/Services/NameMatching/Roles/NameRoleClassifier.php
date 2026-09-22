<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Roles;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Works out which part of the full name each driver token is.
 *
 * Driver names reach us in document order, the order a Central Asian
 * registry writes them in:
 *
 *     SURNAME  GIVEN-NAME  FATHER'S-NAME [OGLI|QIZI]
 *     SURNAME  GIVEN-NAME  PATRONYMIC-OVICH
 *
 * so position carries most of the answer, and the two patronymic
 * spellings carry the rest. Both are used here, position last:
 *
 *  1. a token ending in -ovich/-evich/-ovna/-evna is a patronymic
 *     wherever it stands;
 *  2. a lineage marker (OGLI/QIZI, dropped by the tokenizer as a token
 *     but still present in the name) makes the token in front of it the
 *     father's name -- but only when at least three tokens remain, so a
 *     two-word name whose marker has lost its father's name
 *     ("TOJIMATOV ABDULKODIR UGLI") keeps ABDULKODIR as the given name
 *     instead of demoting the only name we have;
 *  3. of what is left, the first token is the surname and the second the
 *     given name; a single remaining token is the given name, because a
 *     name with nothing around it is the one people go by.
 *
 * Getting this wrong in either direction is cheap: an unknown role is
 * priced exactly like a given name, so a misread structure can only cost
 * a few points, never a match.
 */
final class NameRoleClassifier
{
    /**
     * Slavic patronymic endings, in normalized (transliterated,
     * lowercase) spelling.
     */
    private const PATRONYMIC_SUFFIXES = [
        'ovich', 'evich', 'ovna', 'evna', 'ivich', 'ivna',
    ];

    /**
     * Turkic lineage markers: "son of" / "daughter of". The tokenizer
     * drops them as identity tokens, so they are looked for in the
     * normalized name instead.
     */
    private const LINEAGE_MARKERS = [
        'ogli', 'ogly', 'oglu', 'uly', 'ugli',
        'qizi', 'kizi', 'qizy',
    ];

    /**
     * @param  list<Token>  $tokens  driver tokens, in document order
     * @return list<Token>  the same tokens, each carrying its role
     */
    public function classify(array $tokens, string $normalizedName): array
    {
        if ($tokens === []) {
            return [];
        }

        $roles = [];
        $remaining = [];

        foreach ($tokens as $index => $token) {
            if ($this->isPatronymicSpelling($token->display)) {
                $roles[$index] = NameRole::Patronymic;

                continue;
            }

            $remaining[] = $index;
        }

        /*
         * Rule 2: the father's name in front of OGLI/QIZI.
         */
        if (
            count($remaining) >= 3
            && $this->hasLineageMarker($normalizedName)
        ) {
            $roles[array_pop($remaining)] = NameRole::Patronymic;
        }

        /*
         * Rule 3: document order does the rest.
         */
        if (count($remaining) >= 2) {
            $roles[$remaining[0]] = NameRole::Surname;
            $roles[$remaining[1]] = NameRole::GivenName;
        } elseif (count($remaining) === 1) {
            $roles[$remaining[0]] = NameRole::GivenName;
        }

        $classified = [];

        foreach ($tokens as $index => $token) {
            $classified[] = $token->withRole(
                $roles[$index] ?? NameRole::Unknown,
            );
        }

        return $classified;
    }

    private function isPatronymicSpelling(string $display): bool
    {
        foreach (self::PATRONYMIC_SUFFIXES as $suffix) {
            if (
                strlen($display) > strlen($suffix) + 1
                && str_ends_with($display, $suffix)
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasLineageMarker(string $normalizedName): bool
    {
        foreach (preg_split('/\s+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (in_array($word, self::LINEAGE_MARKERS, true)) {
                return true;
            }
        }

        return false;
    }
}
