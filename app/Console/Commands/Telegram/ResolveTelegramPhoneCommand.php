<?php

declare(strict_types=1);

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
use App\Models\Telegram\TelegramResolvedPhone;
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
         * ============================================================
         * 1. АТОМАРНО ЗАБИРАЕМ CHECK
         * ============================================================
         *
         * Здесь attempts НЕ увеличиваем.
         *
         * Мы только запрещаем другому worker одновременно
         * обрабатывать тот же check.
         */
        $updated = TelegramDriverCheck::query()
            ->whereKey($check->id)
            ->where(
                'status',
                TelegramDriverCheckStatus::Pending
            )
            ->update([
                'status' => TelegramDriverCheckStatus::Processing,
                'error_message' => null,
            ]);

        if ($updated === 0) {
            return self::SUCCESS;
        }

        $check->refresh();

        $account = null;

        try {
            /*
             * ========================================================
             * 2. ИЩЕМ УЖЕ RESOLVED PHONE
             * ========================================================
             *
             * Если номер уже был успешно открыт Telegram,
             * Telegram API больше НЕ вызываем.
             */
            $resolvedPhone = TelegramResolvedPhone::query()
                ->where(
                    'phone_normalized',
                    $check->phone_normalized
                )
                ->first();

            if ($resolvedPhone) {
                $this->info(
                    "Using cached Telegram resolve for check #{$check->id}"
                );

                Log::info(
                    'Telegram resolved phone found in cache',
                    [
                        'check_id' => $check->id,
                        'phone' => $check->phone_normalized,
                        'resolved_phone_id' => $resolvedPhone->id,
                        'telegram_user_id' =>
                            $resolvedPhone->telegram_user_id,
                    ]
                );

                $this->applyResolvedPhone(
                    $check,
                    $resolvedPhone,
                    $nameMatcher,
                );

                return self::SUCCESS;
            }

            /*
             * ========================================================
             * 3. ACQUIRE RESOLVER ACCOUNT
             * ========================================================
             *
             * Handler уже проверял наличие аккаунта.
             *
             * Но между Handler и Worker мог произойти race:
             * другой worker мог забрать последний аккаунт.
             *
             * Поэтому здесь обязательно делаем настоящий acquire.
             */
            $account = $processService->findAvailableAccount(
                TelegramAccountProcessEnum::ResolverPhone
            );

            if (!$account) {
                /*
                 * Это НЕ ошибка check.
                 *
                 * Возвращаем его в pending.
                 * attempts не увеличиваем.
                 *
                 * Следующая обработка произойдёт,
                 * когда появится доступный account.
                 */
                $check->update([
                    'status' => TelegramDriverCheckStatus::Pending,
                    'error_message' => null,
                ]);

                Log::warning(
                    'No resolver account available during command execution',
                    [
                        'check_id' => $check->id,
                        'phone' => $check->phone_normalized,
                    ]
                );

                return self::SUCCESS;
            }

            /*
             * ========================================================
             * 4. НАСТОЯЩАЯ ПОПЫТКА RESOLVE
             * ========================================================
             *
             * Только сейчас увеличиваем attempts.
             */
            $updated = TelegramDriverCheck::query()
                ->whereKey($check->id)
                ->where(
                    'status',
                    TelegramDriverCheckStatus::Processing
                )
                ->where(
                    'attempts',
                    '<',
                    3
                )
                ->update([
                    'attempts' => $check->attempts + 1,
                ]);

            if ($updated === 0) {
                /*
                 * На всякий случай.
                 *
                 * Не оставляем account занятым.
                 */
                $check->refresh();

                return self::SUCCESS;
            }

            $check->refresh();

            $this->info(
                "Resolving check #{$check->id}, "
                . "attempt {$check->attempts}/3"
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
             * ========================================================
             * 5. START MADELINEPROTO
             * ========================================================
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
             * ========================================================
             * 6. RESOLVE PHONE
             * ========================================================
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
             * ========================================================
             * 7. TELEGRAM USER НЕ НАЙДЕН
             * ========================================================
             *
             * Номер не зарегистрирован в Telegram.
             *
             * Это нормальный ответ Telegram.
             * Resolver account не штрафуем.
             */
            if (
                !($result['success'] ?? false)
                && ($result['reason'] ?? null)
                    === 'telegram_not_registered'
            ) {
                $check->update([
                    'status' =>
                        TelegramDriverCheckStatus::NotConfirmed,

                    'error_message' => null,

                    'telegram_raw' =>
                        $result['raw'] ?? null,

                    'checked_at' => now(),
                ]);

                return self::SUCCESS;
            }

            /*
             * ========================================================
             * 8. TELEGRAM / ACCOUNT ERROR
             * ========================================================
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
             * ========================================================
             * 9. TELEGRAM USER
             * ========================================================
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
             * Данные пользователя.
             */
            $telegramUserId = isset($user['id'])
                ? (int) $user['id']
                : null;

            $telegramUsername =
                $user['username'] ?? null;

            $telegramFirstName =
                $user['first_name'] ?? null;

            $telegramLastName =
                $user['last_name'] ?? null;

            /*
             * ========================================================
             * 10. Сохраняем RESOLVED PHONE
             * ========================================================
             *
             * Это наш постоянный cache успешного resolve.
             */
            $resolvedPhone = TelegramResolvedPhone::query()
                ->updateOrCreate(
                    [
                        'phone_normalized' =>
                            $check->phone_normalized,
                    ],
                    [
                        'telegram_user_id' =>
                            $telegramUserId,

                        'telegram_username' =>
                            $telegramUsername,

                        'telegram_first_name' =>
                            $telegramFirstName,

                        'telegram_last_name' =>
                            $telegramLastName,

                        'telegram_raw' =>
                            $result['raw'] ?? null,

                        'telegram_account_id' =>
                            $account->id,

                        'resolved_at' => now(),
                    ]
                );

            /*
             * Успешный resolve для resolver account.
             */
            $processService->registerSuccess(
                $account,
                TelegramAccountProcessEnum::ResolverPhone
            );

            Log::info(
                'Telegram resolved phone saved',
                [
                    'check_id' => $check->id,
                    'resolved_phone_id' =>
                        $resolvedPhone->id,
                    'phone' =>
                        $resolvedPhone->phone_normalized,
                    'telegram_user_id' =>
                        $resolvedPhone->telegram_user_id,
                    'account_id' => $account->id,
                ]
            );

            /*
             * ========================================================
             * 11. APPLY TELEGRAM DATA TO CHECK
             * ========================================================
             */
            $this->applyResolvedPhone(
                $check,
                $resolvedPhone,
                $nameMatcher,
            );

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
             * ========================================================
             * 12. RELEASE
             * ========================================================
             *
             * Освобождаем resolver account независимо
             * от результата выполнения.
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
     * Apply already resolved Telegram data to the check
     * and perform name matching.
     */
    private function applyResolvedPhone(
        TelegramDriverCheck $check,
        TelegramResolvedPhone $resolvedPhone,
        TelegramNameMatcher $nameMatcher,
    ): void {
        $telegramRaw =
            $resolvedPhone->telegram_raw ?? [];

        $telegramRaw['resolved_from_cache'] = true;

        $telegramRaw['resolved_phone_id'] =
            $resolvedPhone->id;

        /*
         * Копируем Telegram user данные.
         */
        $check->update([
            'telegram_user_id' =>
                $resolvedPhone->telegram_user_id,

            'telegram_username' =>
                $resolvedPhone->telegram_username,

            'telegram_first_name' =>
                $resolvedPhone->telegram_first_name,

            'telegram_last_name' =>
                $resolvedPhone->telegram_last_name,

            'telegram_raw' => $telegramRaw,

            'error_message' => null,
        ]);

        /*
         * ============================================================
         * NameMatcher
         * ============================================================
         */
        $match = $nameMatcher->match(
            $check->driver_name,
            $resolvedPhone->telegram_first_name,
            $resolvedPhone->telegram_last_name,
        );

        $telegramRaw['name_match'] = $match;

        $check->update([
            'telegram_raw' => $telegramRaw,

            'status' =>
                ($match['matched'] ?? false)
                    ? TelegramDriverCheckStatus::Confirmed
                    : TelegramDriverCheckStatus::NotConfirmed,

            'checked_at' => now(),
        ]);

        Log::info(
            'Telegram driver name matching completed',
            [
                'check_id' => $check->id,
                'phone' => $check->phone_normalized,
                'resolved_phone_id' =>
                    $resolvedPhone->id,
                'matched' => $match['matched'] ?? false,
                'score' => $match['score'] ?? 0,
                'level' => $match['level'] ?? null,
                'resolved_from_cache' =>
                    $telegramRaw['resolved_from_cache'] ?? false,
            ]
        );

        if ($match['matched'] ?? false) {
            $this->info(
                "✅ Check #{$check->id} confirmed."
            );
        } else {
            $this->warn(
                "❌ Check #{$check->id} not confirmed."
            );
        }
    }

    /**
     * Retry processing if attempts remain.
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
                'status' =>
                    TelegramDriverCheckStatus::Pending,

                'error_message' => $error,
            ]);

            ResolveTelegramPhoneJob::dispatch(
                $check->id
            )->onQueue('telegram');

            Log::warning(
                'Telegram driver check scheduled for retry',
                [
                    'check_id' => $check->id,
                    'attempts' => $check->attempts,
                    'error' => $error,
                ]
            );

            return self::SUCCESS;
        }

        /*
         * Три реальные попытки исчерпаны.
         */
        $check->update([
            'status' =>
                TelegramDriverCheckStatus::NotConfirmed,

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