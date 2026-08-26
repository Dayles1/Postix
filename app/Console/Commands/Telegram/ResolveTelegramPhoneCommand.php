<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\MadelineService;
use App\Application\Telegram\Services\TelegramAccountProcessService;
use App\Application\Telegram\Services\TelegramContactResolver;
use App\Application\Telegram\Services\TelegramNameMatcher;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolveTelegramPhoneCommand extends Command
{
    protected $signature =
        'telegram:resolve-phone {checkId}';

    protected $description =
        'Resolve driver phone using five different random Telegram accounts';

    private const MAX_ATTEMPTS = 5;

    public function handle(
        MadelineService $madelineService,
        TelegramContactResolver $resolver,
        TelegramNameMatcher $nameMatcher,
        TelegramAccountProcessService $processService,
    ): int {
        $checkId =
            (int) $this->argument(
                'checkId',
            );

        $check =
            TelegramDriverCheck::query()
                ->find($checkId);

        if (!$check) {
            $this->error(
                "Check #{$checkId} not found.",
            );

            Log::warning(
                'ResolveTelegramPhoneCommand check not found',
                [
                    'check_id' =>
                        $checkId,
                ],
            );

            return self::FAILURE;
        }

        /*
         * Already finished.
         */
        if (
            $check->status ===
                TelegramDriverCheckStatus::Confirmed
            ||
            $check->status ===
                TelegramDriverCheckStatus::NotConfirmed
        ) {
            return self::SUCCESS;
        }

        /*
         * Phone is missing.
         */
        if (!$check->phone_normalized) {
            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NotConfirmed,

                'error_message' =>
                    'Phone number is missing.',

                'checked_at' =>
                    now(),
            ]);

            return self::SUCCESS;
        }

        /*
         * ================================================================
         * ATOMICALLY CLAIM CHECK
         * ================================================================
         */
        $claimed =
            TelegramDriverCheck::query()
                ->whereKey($check->id)
                ->where(
                    'status',
                    TelegramDriverCheckStatus::Pending,
                )
                ->update([
                    'status' =>
                        TelegramDriverCheckStatus::Processing,

                    'error_message' =>
                        null,
                ]);

        if ($claimed === 0) {
            return self::SUCCESS;
        }

        $check->refresh();

        try {
            /*
             * ============================================================
             * CACHE
             * ============================================================
             *
             * If this phone was already resolved successfully,
             * do not call Telegram again.
             */
            $resolvedPhone =
                TelegramResolvedPhone::query()
                    ->where(
                        'phone_normalized',
                        $check->phone_normalized,
                    )
                    ->first();

            if ($resolvedPhone) {
                Log::info(
                    'Telegram resolved phone found in cache',
                    [
                        'check_id' =>
                            $check->id,

                        'phone' =>
                            $check->phone_normalized,

                        'resolved_phone_id' =>
                            $resolvedPhone->id,

                        'telegram_user_id' =>
                            $resolvedPhone->telegram_user_id,
                    ],
                );

                $this->applyResolvedPhone(
                    $check,
                    $resolvedPhone,
                    $nameMatcher,
                );

                return self::SUCCESS;
            }

            /*
             * ============================================================
             * ATTEMPT STATE
             * ============================================================
             */
            $telegramRaw =
                $this->getTelegramRaw(
                    $check,
                );

            $telegramRaw[
                'resolver_attempts'
            ] = [];

            $telegramRaw[
                'resolver_attempt_account_ids'
            ] = [];

            $telegramRaw[
                'resolver_not_registered_count'
            ] = 0;

            $telegramRaw[
                'resolver_real_error_count'
            ] = 0;

            $telegramRaw[
                'resolver_result'
            ] = null;

            $check->update([
                'attempts' => 0,
                'telegram_raw' => $telegramRaw,
            ]);

            /*
             * Accounts used only during THIS check.
             */
            $usedAccountIds = [];

            /*
             * Number of attempts where target phone
             * was not registered.
             */
            $notRegisteredCount = 0;

            /*
             * Actual resolver/account errors.
             */
            $realErrorCount = 0;

            $lastError =
                'Telegram resolve failed.';

            /*
             * ============================================================
             * FIVE ATTEMPTS
             * ============================================================
             */
            for (
                $attempt = 1;
                $attempt <= self::MAX_ATTEMPTS;
                $attempt++
            ) {
                /*
                 * ========================================================
                 * CLAIM RANDOM ACCOUNT
                 * ========================================================
                 */
                $account =
                    $processService
                        ->claimRandomAvailableAccount(
                            TelegramAccountProcessEnum::ResolverPhone,
                            $usedAccountIds,
                        );

                /*
                 * ========================================================
                 * NO ACCOUNT
                 * ========================================================
                 *
                 * No notification.
                 *
                 * Just finish this check with an explicit
                 * resolver_unavailable result.
                 */
                if (!$account) {
                    $message =
                        'Resolver accounts are unavailable.';

                    $telegramRaw =
                        $this->getTelegramRaw(
                            $check,
                        );

                    $telegramRaw[
                        'resolver_result'
                    ] =
                        'resolver_unavailable';

                    $telegramRaw[
                        'resolver_unavailable_at_attempt'
                    ] =
                        $attempt;

                    $telegramRaw[
                        'resolver_used_account_ids'
                    ] =
                        $usedAccountIds;

                    $telegramRaw[
                        'resolver_not_registered_count'
                    ] =
                        $notRegisteredCount;

                    $telegramRaw[
                        'resolver_real_error_count'
                    ] =
                        $realErrorCount;

                    $check->update([
                        'status' =>
                            TelegramDriverCheckStatus::NotConfirmed,

                        'error_message' =>
                            $message,

                        'telegram_raw' =>
                            $telegramRaw,

                        'checked_at' =>
                            now(),
                    ]);

                    Log::warning(
                        'Telegram resolver accounts unavailable',
                        [
                            'check_id' =>
                                $check->id,

                            'phone' =>
                                $check->phone_normalized,

                            'attempt' =>
                                $attempt,

                            'used_account_ids' =>
                                $usedAccountIds,
                        ],
                    );

                    return self::SUCCESS;
                }

                $accountId =
                    (int) $account->id;

                /*
                 * Account can never be reused in this check.
                 */
                $usedAccountIds[] =
                    $accountId;

                $usedAccountIds =
                    array_values(
                        array_unique(
                            $usedAccountIds,
                        ),
                    );

                /*
                 * Save current attempt information.
                 */
                $telegramRaw =
                    $this->getTelegramRaw(
                        $check,
                    );

                $telegramRaw[
                    'resolver_current_attempt'
                ] =
                    $attempt;

                $telegramRaw[
                    'resolver_last_account_id'
                ] =
                    $accountId;

                $telegramRaw[
                    'resolver_last_account_phone'
                ] =
                    $account->phone;

                $telegramRaw[
                    'resolver_attempt_account_ids'
                ] =
                    $usedAccountIds;

                $check->update([
                    'attempts' =>
                        $attempt,

                    'telegram_raw' =>
                        $telegramRaw,
                ]);

                $api = null;

                try {
                    /*
                     * ====================================================
                     * START MADELINE
                     * ====================================================
                     */
                    $this->info(
                        sprintf(
                            '[%d/%d] Using account %s',
                            $attempt,
                            self::MAX_ATTEMPTS,
                            $account->phone,
                        ),
                    );

                    Log::info(
                        'Telegram resolver attempt started',
                        [
                            'check_id' =>
                                $check->id,

                            'attempt' =>
                                $attempt,

                            'account_id' =>
                                $accountId,

                            'phone' =>
                                $account->phone,

                            'used_account_ids' =>
                                $usedAccountIds,
                        ],
                    );

                    $api =
                        $madelineService->for(
                            $account,
                        );

                    if (!$api) {
                        throw new \RuntimeException(
                            'Failed to start MadelineProto.',
                        );
                    }

                    /*
                     * ====================================================
                     * RESOLVE PHONE
                     * ====================================================
                     */
                    $result =
                        $resolver->resolve(
                            $api,
                            $check->phone_normalized,
                        );

                    $success =
                        (bool) (
                            $result['success']
                            ?? false
                        );

                    $reason =
                        (string) (
                            $result['reason']
                            ?? 'telegram_error'
                        );

                    $errorMessage =
                        $result['error_message']
                        ?? null;

                    Log::info(
                        'Telegram phone resolve result',
                        [
                            'check_id' =>
                                $check->id,

                            'attempt' =>
                                $attempt,

                            'account_id' =>
                                $accountId,

                            'phone' =>
                                $check->phone_normalized,

                            'success' =>
                                $success,

                            'reason' =>
                                $reason,

                            'error_message' =>
                                $errorMessage,
                        ],
                    );

                    /*
                     * ====================================================
                     * SAVE ATTEMPT HISTORY
                     * ====================================================
                     */
                    $telegramRaw =
                        $this->getTelegramRaw(
                            $check,
                        );

                    $attemptHistory =
                        $telegramRaw[
                            'resolver_attempts'
                        ]
                        ?? [];

                    if (!is_array(
                        $attemptHistory,
                    )) {
                        $attemptHistory = [];
                    }

                    $attemptHistory[] = [
                        'attempt' =>
                            $attempt,

                        'account_id' =>
                            $accountId,

                        'account_phone' =>
                            $account->phone,

                        'success' =>
                            $success,

                        'reason' =>
                            $reason,

                        'error_message' =>
                            $errorMessage,

                        'at' =>
                            now()->toISOString(),
                    ];

                    $telegramRaw[
                        'resolver_attempts'
                    ] =
                        $attemptHistory;

                    $check->update([
                        'telegram_raw' =>
                            $telegramRaw,
                    ]);

                    /*
                     * ====================================================
                     * NOT REGISTERED
                     * ====================================================
                     *
                     * VERY IMPORTANT:
                     *
                     * This does NOT mean resolver account is broken.
                     */
                    if (
                        !$success
                        &&
                        $reason ===
                            'telegram_not_registered'
                    ) {
                        $notRegisteredCount++;

                        $processService
                            ->registerNotFound(
                                $account,
                                TelegramAccountProcessEnum::ResolverPhone,
                                $reason,
                            );

                        $telegramRaw =
                            $this->getTelegramRaw(
                                $check,
                            );

                        $telegramRaw[
                            'resolver_not_registered_count'
                        ] =
                            $notRegisteredCount;

                        $check->update([
                            'telegram_raw' =>
                                $telegramRaw,
                        ]);

                        $this->warn(
                            sprintf(
                                '[%d/%d] %s -> NOT REGISTERED',
                                $attempt,
                                self::MAX_ATTEMPTS,
                                $account->phone,
                            ),
                        );

                        /*
                         * Next random account.
                         */
                        continue;
                    }

                    /*
                     * ====================================================
                     * REAL ACCOUNT / TELEGRAM ERROR
                     * ====================================================
                     */
                    if (!$success) {
                        $realErrorCount++;

                        $lastError =
                            (string) (
                                $errorMessage
                                ?? $reason
                            );

                        $state =
                            $processService
                                ->registerFailure(
                                    $account,
                                    TelegramAccountProcessEnum::ResolverPhone,
                                    $reason,
                                );

                        Log::warning(
                            'Telegram resolver account failure',
                            [
                                'check_id' =>
                                    $check->id,

                                'attempt' =>
                                    $attempt,

                                'account_id' =>
                                    $accountId,

                                'phone' =>
                                    $account->phone,

                                'reason' =>
                                    $reason,

                                'error' =>
                                    $lastError,

                                'failures' =>
                                    $state->failures,

                                'consecutive_failures' =>
                                    $state->consecutive_failures,

                                'is_available' =>
                                    $state->is_available,
                            ],
                        );

                        continue;
                    }

                    /*
                     * ====================================================
                     * SUCCESS BUT USER IS MISSING
                     * ====================================================
                     */
                    $user =
                        $result['user']
                        ?? null;

                    if (!$user) {
                        $realErrorCount++;

                        $lastError =
                            'Telegram returned no user.';

                        $state =
                            $processService
                                ->registerFailure(
                                    $account,
                                    TelegramAccountProcessEnum::ResolverPhone,
                                    'telegram_user_missing',
                                );

                        Log::warning(
                            'Telegram resolver returned no user',
                            [
                                'check_id' =>
                                    $check->id,

                                'attempt' =>
                                    $attempt,

                                'account_id' =>
                                    $accountId,

                                'failures' =>
                                    $state->failures,

                                'consecutive_failures' =>
                                    $state->consecutive_failures,
                            ],
                        );

                        continue;
                    }

                    /*
                     * ====================================================
                     * EXTRACT USER
                     * ====================================================
                     */
                    $telegramUserId =
                        isset($user['id'])
                            ? (int) $user['id']
                            : null;

                    $telegramUsername =
                        $user['username']
                        ?? null;

                    $telegramFirstName =
                        $user['first_name']
                        ?? null;

                    $telegramLastName =
                        $user['last_name']
                        ?? null;

                    /*
                     * ====================================================
                     * SAVE RESOLVED PHONE
                     * ====================================================
                     */
                    $resolvedPhone =
                        TelegramResolvedPhone::query()
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
                                        $result['raw']
                                        ?? null,

                                    'telegram_account_id' =>
                                        $accountId,

                                    'resolved_at' =>
                                        now(),
                                ],
                            );

                    /*
                     * ====================================================
                     * ACCOUNT SUCCESS
                     * ====================================================
                     */
                    $state =
                        $processService
                            ->registerSuccess(
                                $account,
                                TelegramAccountProcessEnum::ResolverPhone,
                            );

                    /*
                     * ====================================================
                     * SAVE RESULT
                     * ====================================================
                     */
                    $telegramRaw =
                        $this->getTelegramRaw(
                            $check,
                        );

                    $telegramRaw[
                        'resolver_result'
                    ] =
                        'registered';

                    $telegramRaw[
                        'resolver_success_attempt'
                    ] =
                        $attempt;

                    $telegramRaw[
                        'resolver_success_account_id'
                    ] =
                        $accountId;

                    $telegramRaw[
                        'resolver_not_registered_count'
                    ] =
                        $notRegisteredCount;

                    $telegramRaw[
                        'resolver_real_error_count'
                    ] =
                        $realErrorCount;

                    $telegramRaw[
                        'resolver_success_account_failures'
                    ] =
                        $state->failures;

                    $telegramRaw[
                        'resolver_success_account_consecutive_failures'
                    ] =
                        $state->consecutive_failures;

                    $check->update([
                        'telegram_raw' =>
                            $telegramRaw,
                    ]);

                    Log::info(
                        'Telegram phone resolved successfully',
                        [
                            'check_id' =>
                                $check->id,

                            'phone' =>
                                $check->phone_normalized,

                            'attempt' =>
                                $attempt,

                            'account_id' =>
                                $accountId,

                            'telegram_user_id' =>
                                $telegramUserId,

                            'not_registered_count' =>
                                $notRegisteredCount,
                        ],
                    );

                    /*
                     * Name matching + final status.
                     */
                    $this->applyResolvedPhone(
                        $check,
                        $resolvedPhone,
                        $nameMatcher,
                    );

                    return self::SUCCESS;

                } catch (Throwable $e) {
                    /*
                     * ====================================================
                     * ATTEMPT EXCEPTION
                     * ====================================================
                     */
                    $realErrorCount++;

                    $lastError =
                        mb_substr(
                            $e->getMessage(),
                            0,
                            1000,
                        );

                    $state =
                        $processService
                            ->registerFailure(
                                $account,
                                TelegramAccountProcessEnum::ResolverPhone,
                                'command_exception',
                            );

                    Log::error(
                        'Telegram resolver attempt exception',
                        [
                            'check_id' =>
                                $check->id,

                            'attempt' =>
                                $attempt,

                            'account_id' =>
                                $accountId,

                            'phone' =>
                                $account->phone,

                            'error' =>
                                $lastError,

                            'exception' =>
                                $e::class,

                            'failures' =>
                                $state->failures,

                            'consecutive_failures' =>
                                $state->consecutive_failures,

                            'is_available' =>
                                $state->is_available,
                        ],
                    );

                    /*
                     * Continue with another account.
                     */
                    continue;

                } finally {
                    /*
                     * ====================================================
                     * STOP MADELINE
                     * ====================================================
                     */
                    if ($api) {
                        try {
                            $api->stop();

                            Log::info(
                                'MadelineProto stopped',
                                [
                                    'check_id' =>
                                        $check->id,

                                    'attempt' =>
                                        $attempt,

                                    'account_id' =>
                                        $accountId,
                                ],
                            );
                        } catch (Throwable $e) {
                            Log::warning(
                                'Failed to stop MadelineProto',
                                [
                                    'check_id' =>
                                        $check->id,

                                    'attempt' =>
                                        $attempt,

                                    'account_id' =>
                                        $accountId,

                                    'error' =>
                                        $e->getMessage(),

                                    'exception' =>
                                        $e::class,
                                ],
                            );
                        }
                    }

                    /*
                     * ====================================================
                     * RELEASE ACCOUNT
                     * ====================================================
                     */
                    $processService->release(
                        $account,
                        TelegramAccountProcessEnum::ResolverPhone,
                    );
                }
            }

            /*
             * ============================================================
             * FIVE ATTEMPTS FINISHED
             * ============================================================
             */
            $check->refresh();

            $telegramRaw =
                $this->getTelegramRaw(
                    $check,
                );

            $telegramRaw[
                'resolver_finished'
            ] = true;

            $telegramRaw[
                'resolver_attempts_count'
            ] =
                self::MAX_ATTEMPTS;

            $telegramRaw[
                'resolver_not_registered_count'
            ] =
                $notRegisteredCount;

            $telegramRaw[
                'resolver_real_error_count'
            ] =
                $realErrorCount;

            /*
             * ============================================================
             * ALL FIVE = NOT REGISTERED
             * ============================================================
             */
            if (
                $notRegisteredCount ===
                self::MAX_ATTEMPTS
            ) {
                $telegramRaw[
                    'resolver_result'
                ] =
                    'phone_not_registered';

                $check->update([
                    'status' =>
                        TelegramDriverCheckStatus::NotConfirmed,

                    'error_message' =>
                        null,

                    'telegram_raw' =>
                        $telegramRaw,

                    'checked_at' =>
                        now(),
                ]);

                $this->warn(
                    "❌ {$check->phone_normalized} "
                    . 'is NOT REGISTERED on Telegram.',
                );

                return self::SUCCESS;
            }

            /*
             * ============================================================
             * SOME REAL ERRORS
             * ============================================================
             */
            $telegramRaw[
                'resolver_result'
            ] =
                'resolver_failed_without_match';

            $telegramRaw[
                'resolver_error'
            ] =
                $lastError;

            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NotConfirmed,

                'error_message' =>
                    $lastError,

                'telegram_raw' =>
                    $telegramRaw,

                'checked_at' =>
                    now(),
            ]);

            Log::warning(
                'Telegram resolver completed without successful match',
                [
                    'check_id' =>
                        $check->id,

                    'phone' =>
                        $check->phone_normalized,

                    'attempts' =>
                        self::MAX_ATTEMPTS,

                    'not_registered_count' =>
                        $notRegisteredCount,

                    'real_error_count' =>
                        $realErrorCount,

                    'used_account_ids' =>
                        $usedAccountIds,

                    'error' =>
                        $lastError,
                ],
            );

            return self::FAILURE;

        } catch (Throwable $e) {
            /*
             * ============================================================
             * GLOBAL ERROR
             * ============================================================
             */
            $error =
                mb_substr(
                    $e->getMessage(),
                    0,
                    1000,
                );

            Log::error(
                'ResolveTelegramPhoneCommand failed',
                [
                    'check_id' =>
                        $check->id,

                    'phone' =>
                        $check->phone_normalized,

                    'attempts' =>
                        $check->attempts,

                    'error' =>
                        $error,

                    'exception' =>
                        $e::class,
                ],
            );

            $check->refresh();

            $telegramRaw =
                $this->getTelegramRaw(
                    $check,
                );

            $telegramRaw[
                'resolver_result'
            ] =
                'command_failed';

            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NotConfirmed,

                'error_message' =>
                    $error,

                'telegram_raw' =>
                    $telegramRaw,

                'checked_at' =>
                    now(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Convert telegram_raw to array.
     */
    private function getTelegramRaw(
        TelegramDriverCheck $check,
    ): array {
        $raw =
            $check->telegram_raw;

        if (is_array($raw)) {
            return $raw;
        }

        if (
            is_string($raw)
            && $raw !== ''
        ) {
            $decoded =
                json_decode(
                    $raw,
                    true,
                );

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Apply resolved Telegram user to check.
     *
     * Then run name matching.
     */
    private function applyResolvedPhone(
        TelegramDriverCheck $check,
        TelegramResolvedPhone $resolvedPhone,
        TelegramNameMatcher $nameMatcher,
    ): void {
        $telegramRaw =
            $resolvedPhone->telegram_raw
            ?? [];

        if (!is_array(
            $telegramRaw,
        )) {
            $telegramRaw = [];
        }

        $telegramRaw[
            'resolved_from_cache'
        ] =
            false;

        $telegramRaw[
            'resolved_phone_id'
        ] =
            $resolvedPhone->id;

        $check->update([
            'telegram_user_id' =>
                $resolvedPhone->telegram_user_id,

            'telegram_username' =>
                $resolvedPhone->telegram_username,

            'telegram_first_name' =>
                $resolvedPhone->telegram_first_name,

            'telegram_last_name' =>
                $resolvedPhone->telegram_last_name,

            'telegram_raw' =>
                $telegramRaw,

            'error_message' =>
                null,
        ]);

        /*
         * Name matching.
         */
        $match =
            $nameMatcher->match(
                $check->driver_name,
                $resolvedPhone->telegram_first_name,
                $resolvedPhone->telegram_last_name,
            );

        $telegramRaw[
            'name_match'
        ] =
            $match;

        $check->update([
            'telegram_raw' =>
                $telegramRaw,

            'status' =>
                ($match['matched'] ?? false)
                    ? TelegramDriverCheckStatus::Confirmed
                    : TelegramDriverCheckStatus::NotConfirmed,

            'checked_at' =>
                now(),
        ]);

        if (
            $match['matched'] ?? false
        ) {
            $this->info(
                "✅ Check #{$check->id} confirmed.",
            );
        } else {
            $this->warn(
                "❌ Check #{$check->id} not confirmed.",
            );
        }
    }
}