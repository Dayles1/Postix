<?php

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\MadelineService;
use App\Application\Telegram\Services\TelegramAccountProcessService;
use App\Application\Telegram\Services\TelegramContactResolver;
use App\Application\Telegram\Services\TelegramNameMatcher;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Jobs\Telegram\ResolveTelegramPhoneJob;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveTelegramPhoneCommand extends Command
{
    protected $signature = 'telegram:resolve-phone {checkId}';

    protected $description =
        'Resolve driver phone using an available Telegram account';

    public function handle(
        MadelineService $madelineService,
        TelegramContactResolver $resolver,
        TelegramNameMatcher $nameMatcher,
        TelegramAccountProcessService $processService,
    ): int {
        $checkId = (int) $this->argument('checkId');

        $check = TelegramDriverCheck::query()
            ->find($checkId);

        if (!$check) {
            $this->error(
                "Check #{$checkId} not found."
            );

            Log::warning(
                'Resolve phone check not found',
                [
                    'check_id' => $checkId,
                ]
            );

            return self::FAILURE;
        }

        /*
         * Уже завершено.
         */
        if (
            $check->status === TelegramDriverCheckStatus::Confirmed
            || $check->status === TelegramDriverCheckStatus::NotConfirmed
        ) {
            return self::SUCCESS;
        }

        /*
         * Телефона нет.
         */
        if (!$check->phone_normalized) {
            $check->update([
                'status' => TelegramDriverCheckStatus::NotConfirmed,
                'error_message' => 'Phone number is missing.',
                'checked_at' => now(),
            ]);

            return self::SUCCESS;
        }

        /*
         * Атомарно:
         *
         * pending -> processing
         * attempts + 1
         *
         * Только один процесс сможет успешно обновить строку.
         */
        $updated = TelegramDriverCheck::query()
            ->whereKey($check->id)
            ->where(
                'status',
                TelegramDriverCheckStatus::Pending
            )
            ->where(
                'attempts',
                '<',
                3
            )
            ->update([
                'status' => TelegramDriverCheckStatus::Processing,
                'attempts' => $check->attempts + 1,
                'error_message' => null,
            ]);

        if ($updated === 0) {
            return self::SUCCESS;
        }

        $check->refresh();

        $this->info(
            "Resolving check #{$check->id}, attempt {$check->attempts}/3"
        );

        $account = null;

        try {
            /*
             * Ищем и СРАЗУ резервируем аккаунт.
             *
             * Основной listener account автоматически исключён
             * внутри TelegramAccountProcessService.
             */
            $account = $processService->findAvailableAccount(
                TelegramAccountProcessEnum::ResolverPhone
            );

            if (!$account) {
                /*
                 * Нет свободного аккаунта.
                 *
                 * Это не ошибка номера.
                 * Возвращаем check в pending.
                 */
                $check->update([
                    'status' => TelegramDriverCheckStatus::Pending,
                    'error_message' => 'No resolver account available.',
                ]);

                Log::warning(
                    'No resolver account available',
                    [
                        'check_id' => $check->id,
                        'attempt' => $check->attempts,
                    ]
                );

                return self::SUCCESS;
            }

            $this->info(
                "Using account #{$account->id} ({$account->phone})"
            );

            Log::info(
                'Resolver account selected',
                [
                    'check_id' => $check->id,
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'attempt' => $check->attempts,
                ]
            );

            /*
             * Запускаем MadelineProto для выбранного аккаунта.
             */
            $api = $madelineService->for($account);

            if (!$api) {
                $processService->registerFailure(
                    $account,
                    TelegramAccountProcessEnum::ResolverPhone,
                    'madeline_start_failed',
                    3
                );

                return $this->retryOrFail(
                    $check,
                    'Failed to start MadelineProto.'
                );
            }

            /*
             * Resolve phone.
             */
            $result = $resolver->resolve(
                $api,
                $check->phone_normalized
            );

            Log::info(
                'Telegram phone resolve result',
                [
                    'check_id' => $check->id,
                    'account_id' => $account->id,
                    'phone' => $check->phone_normalized,
                    'success' => $result['success'] ?? false,
                    'reason' => $result['reason'] ?? null,
                ]
            );

            /*
             * Номер НЕ зарегистрирован в Telegram.
             *
             * Telegram штатно ответил.
             * Это НЕ failure аккаунта.
             */
            if (
                !($result['success'] ?? false)
                && ($result['reason'] ?? null)
                    === 'telegram_not_registered'
            ) {
                $check->update([
                    'status' => TelegramDriverCheckStatus::NotConfirmed,
                    'error_message' => null,
                    'telegram_raw' => $result['raw'] ?? null,
                    'checked_at' => now(),
                ]);

                return self::SUCCESS;
            }

            /*
             * Ошибка Telegram / аккаунта.
             */
            if (!($result['success'] ?? false)) {
                $reason = (string) (
                    $result['reason']
                    ?? 'telegram_error'
                );

                $errorMessage = (string) (
                    $result['error_message']
                    ?? $reason
                );

                /*
                 * Этот аккаунт получил ошибку.
                 */
                $processService->registerFailure(
                    $account,
                    TelegramAccountProcessEnum::ResolverPhone,
                    $reason,
                    3
                );

                return $this->retryOrFail(
                    $check,
                    $errorMessage
                );
            }

            /*
             * Telegram user найден.
             */
            $user = $result['user'] ?? null;

            if (!$user) {
                $processService->registerFailure(
                    $account,
                    TelegramAccountProcessEnum::ResolverPhone,
                    'telegram_user_missing',
                    3
                );

                return $this->retryOrFail(
                    $check,
                    'Telegram returned no user.'
                );
            }

            /*
             * Успешный resolve.
             */
            $processService->registerSuccess(
                $account,
                TelegramAccountProcessEnum::ResolverPhone
            );

            $telegramUserId = isset($user['id'])
                ? (int) $user['id']
                : null;

            $telegramUsername = $user['username'] ?? null;
            $telegramFirstName = $user['first_name'] ?? null;
            $telegramLastName = $user['last_name'] ?? null;

            $check->update([
                'telegram_user_id' => $telegramUserId,
                'telegram_username' => $telegramUsername,
                'telegram_first_name' => $telegramFirstName,
                'telegram_last_name' => $telegramLastName,
                'telegram_raw' => $result['raw'] ?? null,
                'error_message' => null,
            ]);

            /*
             * Проверяем имя водителя.
             */
            $match = $nameMatcher->match(
                $check->driver_name,
                $telegramFirstName,
                $telegramLastName,
            );

            Log::info(
                'Telegram driver name matching completed',
                [
                    'check_id' => $check->id,
                    'account_id' => $account->id,
                    'matched' => $match['matched'] ?? false,
                    'score' => $match['score'] ?? 0,
                    'level' => $match['level'] ?? null,
                ]
            );

            /*
             * Добавляем информацию о match в raw.
             */
            $check->refresh();

            $telegramRaw = $check->telegram_raw ?? [];

            $telegramRaw['name_match'] = $match;

            $check->update([
                'telegram_raw' => $telegramRaw,

                'status' => ($match['matched'] ?? false)
                    ? TelegramDriverCheckStatus::Confirmed
                    : TelegramDriverCheckStatus::NotConfirmed,

                'checked_at' => now(),
            ]);

            if ($match['matched'] ?? false) {
                $this->info(
                    "✅ Check #{$check->id} confirmed."
                );
            } else {
                $this->warn(
                    "❌ Check #{$check->id} not confirmed."
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $error = mb_substr(
                $e->getMessage(),
                0,
                1000
            );

            Log::error(
                'Telegram resolve command failed',
                [
                    'check_id' => $check->id,
                    'account_id' => $account?->id,
                    'phone' => $check->phone_normalized,
                    'error' => $error,
                    'exception' => $e::class,
                ]
            );

            if ($account) {
                $processService->registerFailure(
                    $account,
                    TelegramAccountProcessEnum::ResolverPhone,
                    'command_exception',
                    3
                );
            }

            return $this->retryOrFail(
                $check,
                $error
            );
        } finally {
            /*
             * В любом случае освобождаем resolver account.
             */
            if ($account) {
                $processService->release(
                    $account,
                    TelegramAccountProcessEnum::ResolverPhone
                );
            }
        }
    }

    /**
     * Повторить обработку, если ещё есть попытки.
     */
    private function retryOrFail(
        TelegramDriverCheck $check,
        string $error
    ): int {
        $check->refresh();

        /*
         * Ещё есть попытки.
         */
        if ($check->attempts < 3) {
            $check->update([
                'status' => TelegramDriverCheckStatus::Pending,
                'error_message' => $error,
            ]);

            ResolveTelegramPhoneJob::dispatch(
                $check->id
            )->onQueue('telegram');

            return self::SUCCESS;
        }

        /*
         * Три попытки исчерпаны.
         */
        $check->update([
            'status' => TelegramDriverCheckStatus::NotConfirmed,
            'error_message' => $error,
            'checked_at' => now(),
        ]);

        Log::warning(
            'Telegram driver check failed after maximum attempts',
            [
                'check_id' => $check->id,
                'attempts' => $check->attempts,
                'error' => $error,
            ]
        );

        return self::FAILURE;
    }
}