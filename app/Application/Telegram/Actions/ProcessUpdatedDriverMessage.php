<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramDriverCheckReporter;
use App\Application\Telegram\Services\TelegramDriverUpdateParser;
use App\Application\Telegram\Services\TelegramOperationUserParser;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Jobs\Telegram\RetireDriverCheckBotMessage;
use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;

/**
 * Handles "👤 Изменены данные водителя" - but only a change of the phone.
 *
 * Operators change a driver's phone after the check has flagged it: the
 * number was mistyped, or belonged to someone else. The new number
 * deserves a check of its own, and the driver's record deserves to end
 * up with whatever that check says - so the check that flagged the
 * driver is found and re-run with the new phone (RerunTelegramDriverCheck),
 * its old report is marked as outdated, and the new verdict is posted
 * under the change message where the operator is looking.
 *
 * A change for a driver this listener never checked is checked from
 * scratch, on the change message itself.
 */
final class ProcessUpdatedDriverMessage
{
    public function __construct(
        private readonly TelegramDriverUpdateParser $parser,
        private readonly TelegramOperationUserParser $operationUserParser,
        private readonly ResolveOperationUser $resolveOperationUser,
        private readonly ResolveTelegramDriver $resolveTelegramDriver,
        private readonly RerunTelegramDriverCheck $rerun,
        private readonly StartTelegramPhoneResolve $startPhoneResolve,
        private readonly TelegramDriverCheckReporter $reporter,
    ) {
    }

    public function execute(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $update,
        string $text,
    ): void {
        $change = $this->parser->parsePhoneChange($text);

        if ($change === null) {
            Log::info(
                'Driver update ignored: the phone did not change',
                ['check_id' => $update->id],
            );

            return;
        }

        $by = $this->operationUserParser->parse($text);

        $raw = is_array($update->telegram_raw) ? $update->telegram_raw : [];
        $raw['phone_change'] = [
            'from' => $change['old_phone_normalized'],
            'to' => $change['new_phone_normalized'],
            'by' => $by,
        ];

        $update->forceFill([
            'driver_name' => $change['driver_name'],
            'phone_raw' => $change['new_phone_raw'],
            'phone_normalized' => $change['new_phone_normalized'],
            'telegram_raw' => $raw,
        ])->save();

        $target = $this->findTarget($update, $change);

        if ($target === null) {
            $this->checkFromScratch($update, $change, $by);

            return;
        }

        if (
            $target->phone_normalized === $change['new_phone_normalized']
            && ! $target->hasTechnicalFailure()
        ) {
            Log::info(
                'Driver update ignored: the check already uses this phone',
                [
                    'check_id' => $update->id,
                    'target_check_id' => $target->id,
                ],
            );

            return;
        }

        $this->retireOldMessages($telegram, $target, $change);

        $this->rerun->execute(
            check: $target,
            trigger: RerunTelegramDriverCheck::TRIGGER_PHONE_CHANGED,
            by: $by,
            phoneRaw: $change['new_phone_raw'],
            phoneNormalized: $change['new_phone_normalized'],
            newReportUnder: (int) $update->telegram_message_id,
        );

        $raw['rerun_check_id'] = $target->id;

        $update->forceFill(['telegram_raw' => $raw])->save();
    }

    /**
     * The check this change corrects: the latest one on the old phone,
     * or failing that the latest one for a driver of that name. Checks
     * in the same group are preferred - the same driver can be checked
     * in more than one.
     *
     * @param  array<string, string|null>  $change
     */
    private function findTarget(
        TelegramDriverCheck $update,
        array $change,
    ): ?TelegramDriverCheck {
        $candidates = fn () => TelegramDriverCheck::query()
            ->whereKeyNot($update->id)
            ->whereIn('type', [
                TelegramDriverMessageType::CREATED_DRIVER,
                TelegramDriverMessageType::UPDATED_DRIVER,
            ])
            ->where('status', '!=', TelegramDriverCheckStatus::Skipped)
            ->whereNotNull('driver_id')
            ->orderByRaw(
                'CASE WHEN telegram_chat_id = ? THEN 0 ELSE 1 END',
                [$update->telegram_chat_id],
            )
            ->orderByDesc('id');

        if ($change['old_phone_normalized'] !== null) {
            $byPhone = $candidates()
                ->where('phone_normalized', $change['old_phone_normalized'])
                ->first();

            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        if ($change['driver_name'] === null) {
            return null;
        }

        $driverIds = TelegramDriver::query()
            ->where('name_normalized', $change['driver_name'])
            ->pluck('id');

        if ($driverIds->isEmpty()) {
            return null;
        }

        return $candidates()
            ->whereIn('driver_id', $driverIds)
            ->first();
    }

    /**
     * The old report and its buttons describe a phone that is no longer
     * the driver's: say so on both, so nobody acts on them.
     *
     * @param  array<string, string|null>  $change
     */
    private function retireOldMessages(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $target,
        array $change,
    ): void {
        $note = sprintf(
            '🔄 Номер изменён: %s ⟶ %s. Повторная проверка - в ответе на сообщение об изменении.',
            $change['old_phone_normalized'] ?? $target->phone_normalized ?? '-',
            $change['new_phone_normalized'],
        );

        if ($target->reported_at !== null) {
            $this->reporter->retire($telegram, $target, $note);
        }

        if ($target->bot_message_id !== null) {
            RetireDriverCheckBotMessage::dispatch(
                (int) $target->telegram_chat_id,
                (int) $target->bot_message_id,
                $note,
            )->onQueue('telegram');
        }
    }

    /**
     * @param  array<string, string|null>  $change
     */
    private function checkFromScratch(
        TelegramDriverCheck $update,
        array $change,
        ?string $by,
    ): void {
        Log::info(
            'Driver update has no earlier check, checking it from scratch',
            [
                'check_id' => $update->id,
                'phone' => $change['new_phone_normalized'],
                'driver_name' => $change['driver_name'],
            ],
        );

        if ($by === null) {
            $update->update([
                'status' => TelegramDriverCheckStatus::NotConfirmed,
                'error_message' => 'Operation user is missing.',
                'checked_at' => now(),
            ]);

            return;
        }

        $operationUser = $this->resolveOperationUser->execute($by);

        if ($change['driver_name'] === null) {
            $update->update([
                'status' => TelegramDriverCheckStatus::NotConfirmed,
                'operation_user_id' => $operationUser->id,
                'error_message' => 'Driver name is missing.',
                'checked_at' => now(),
            ]);

            return;
        }

        $driver = $this->resolveTelegramDriver->execute(
            $operationUser,
            ['driver_name' => $change['driver_name']],
        );

        $update->update([
            'status' => TelegramDriverCheckStatus::Pending,
            'operation_user_id' => $operationUser->id,
            'driver_id' => $driver->id,
        ]);

        $this->startPhoneResolve->execute($update);
    }
}
