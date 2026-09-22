<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

/**
 * Where a Telegram-side token came from. Used so the scoring layer can
 * tell "two tokens from two different fields corroborate each other"
 * apart from "two tokens split out of the same single field/string" --
 * the former is stronger, independent evidence, the latter is really
 * one signal typed once.
 */
enum EvidenceSource: string
{
    case FirstName = 'telegram_first_name';

    /**
     * The first and last name read together, used where the evidence is
     * a property of the whole displayed name rather than of one field --
     * initials, for instance, are routinely split across both fields
     * ("К.Б." / "А.") by the same person in one sitting.
     */
    case DisplayName = 'telegram_display_name';
    case LastName = 'telegram_last_name';
    case Username = 'username';
}
