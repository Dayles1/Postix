<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Roles;

/**
 * Which part of a full name a driver-side token is.
 *
 * The three parts are not equally good evidence, because a Telegram
 * profile is not a document. People put their given name on it -- alone,
 * in a diminutive, in Cyrillic, decorated with emoji -- and only
 * sometimes add the surname. The patronymic essentially never appears.
 *
 * So a matched given name is the signal the whole check is looking for,
 * while a matched surname is weaker (families share it) and a matched
 * patronymic weaker still (it is the father's name, and the one part a
 * profile has no reason to carry). {@see NameRoleClassifier} assigns the
 * role, {@see \App\Application\Telegram\Services\NameMatching\Scoring\MatchScoreAggregator}
 * prices it.
 */
enum NameRole: string
{
    case Surname = 'surname';
    case GivenName = 'given_name';
    case Patronymic = 'patronymic';
    case Unknown = 'unknown';
}
