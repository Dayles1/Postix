<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;

/**
 * Decides what the system may say on a penalty's own thread.
 *
 * ProcessAutoReply asks here before it answers a private reply to a
 * penalty. The rule is the one that used to live inside it (threatened()),
 * moved out unchanged so there is one place to grow: the answer to "ok"
 * after the top level's threat ("boshqaga beraymi?") is not a thank-you.
 */
final class PenaltyAgent
{
    public function __construct(private readonly ClientCheckRulesStore $checkRules) {}

    /**
     * Whether an answer to their reply to $check is allowed. False after
     * the comment of the top level of an operator's ladder - the one that
     * says the request goes to someone else: the reply stays on the
     * penalty, nothing is sent.
     */
    public function mayAnswer(TelegramClientCheck $check, OperationUser $person): bool
    {
        return ! $this->threatened($check, $person);
    }

    /**
     * The penalty's comment was the top level's of an operator's ladder.
     * Sales managers have no such level.
     */
    private function threatened(TelegramClientCheck $check, OperationUser $person): bool
    {
        if ($check->comment_level === null || $person->isSales()) {
            return false;
        }

        $top = $this->checkRules->current()->topLevel($person->roleOrDefault());

        return $top >= 1 && (int) $check->comment_level >= $top;
    }
}
