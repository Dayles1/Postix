<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\ClientCheckEscalation;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\ClientCheckSender;
use App\Application\Telegram\Services\CrmSalesTurn;
use App\Application\Telegram\Services\TelegramPenaltyMessageParser;
use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * A PENALTY message from a watched chat.
 *
 * Records it, finds the person responsible the same way the driver-check
 * flow finds operators - by name, created when unknown, here with the role
 * from "Ответственный (Operation|Sales)" - works out the level and forwards
 * it. The batch comment follows from the listener's cron
 * ({@see ClientCheckSender::flush()}).
 */
final class ProcessClientCheckMessage
{
    public function __construct(
        private readonly TelegramPenaltyMessageParser $parser,
        private readonly ResolveOperationUser $resolveOperationUser,
        private readonly ClientCheckEscalation $escalation,
        private readonly ClientCheckSender $sender,
        private readonly CrmSalesTurn $salesTurn,
    ) {
    }

    /**
     * @param array<string, mixed> $raw what the listener saw, for telegram_raw
     */
    public function execute(
        SimpleEventHandler $telegram,
        int $chatId,
        int $messageId,
        string $text,
        array $raw = [],
    ): ?TelegramClientCheck {
        $parsed = $this->parser->parse($text);

        $check = $this->record($chatId, $messageId, $text, $raw, $parsed);

        if ($check === null) {
            return null;
        }

        $name = $parsed['responsible_name'];

        if ($name === null) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_RESPONSIBLE_MISSING,
            ]);

            Log::info(
                'Client check has nobody responsible',
                [
                    'check_id' => $check->id,
                    'request_number' => $check->request_number,
                ],
            );

            return $check;
        }

        $role = $parsed['responsible_role'] ?? OperationUser::ROLE_OPERATION;

        /*
         * "Актуальный" with a carrier price in the CRM: the operator has
         * done their part, the penalty is the sales manager's - whoever
         * the bot names. Kept in parsed.sales_turn for the journal.
         */
        $salesTurn = $this->salesTurn->find($check->request_number, $check->crm_status);

        if ($salesTurn !== null) {
            $name = $salesTurn['sales_name'];
            $role = OperationUser::ROLE_SALES;

            $check->update([
                'parsed' => [...$parsed, 'sales_turn' => $salesTurn],
            ]);
        }

        $person = $this->resolveOperationUser->execute($name, $role);

        $check->update([
            'operation_user_id' => $person->id,
        ]);

        $check->setRelation('operationUser', $person);

        /*
         * Worked out even when the person cannot be reached: a penalty
         * still counts towards the next level.
         */
        $this->escalation->apply($check, $person);

        /*
         * Switched off in the panel: kept and counted, sent to nobody. It
         * is not picked up later either - turning penalties back on does
         * not unload a backlog on anyone.
         */
        if (! TelegramSetting::clientChecksEnabled()) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_DISABLED,
            ]);

            return $check;
        }

        /*
         * Outside the working hours the bot's penalties are ignored:
         * nobody is written to after 18:00. Kept, so the journal still
         * shows what came in.
         */
        if (! $this->escalation->rules()->withinWorkingHours($check->created_at ?? now())) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_OUTSIDE_HOURS,
            ]);

            return $check;
        }

        /*
         * The same, for this person's role only: operators and sales
         * managers are switched on and off apart.
         */
        if (! TelegramSetting::clientChecksEnabledFor($person->roleOrDefault())) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_ROLE_DISABLED,
            ]);

            return $check;
        }

        /*
         * And at this level: some penalties are for one role only.
         */
        if ($this->escalation->mode($check, $person) === ClientCheckRules::MODE_OFF) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_LEVEL_OFF,
            ]);

            return $check;
        }

        if (! $person->canReceiveDirectMessages()) {
            $check->update([
                'status' => TelegramClientCheckStatus::Skipped,
                'reason' => TelegramClientCheck::REASON_RESPONSIBLE_UNREACHABLE,
            ]);

            Log::info(
                'Client check skipped',
                [
                    'check_id' => $check->id,
                    'operation_user_id' => $person->id,
                    'dm_enabled' => $person->dm_enabled,
                    'has_peer' => $person->hasTelegramPeer(),
                ],
            );

            return $check;
        }

        $this->sender->forward($telegram, $check);

        return $check;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $parsed
     */
    private function record(
        int $chatId,
        int $messageId,
        string $text,
        array $raw,
        array $parsed,
    ): ?TelegramClientCheck {
        if ($chatId === 0 || $messageId <= 0) {
            return null;
        }

        $exists = TelegramClientCheck::query()
            ->where('telegram_chat_id', $chatId)
            ->where('telegram_message_id', $messageId)
            ->exists();

        if ($exists) {
            return null;
        }

        try {
            return TelegramClientCheck::query()->create([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
                'message_text' => $text,
                'telegram_raw' => $raw !== [] ? $raw : null,
                'parsed' => $parsed,
                'request_number' => $parsed['request_number'],
                'repeat_number' => $parsed['repeat_number'],
                'crm_status' => $parsed['crm_status'],
                'status_since' => $parsed['status_since'],
                'responsible_role' => $parsed['responsible_role'],
                'responsible_name' => $parsed['responsible_name'],
                'status' => TelegramClientCheckStatus::Pending,
                'attempts' => 0,
            ]);
        } catch (QueryException $e) {
            /*
             * The unique index caught a duplicate the check above missed.
             */
            if ($e->getCode() === '23000') {
                return null;
            }

            throw $e;
        }
    }
}
