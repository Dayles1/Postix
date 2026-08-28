<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramDriverCheckReporter
{
    public function send(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $check,
        ?array $match = null,
    ): void {
        try {
            $message = $this->buildMessage(
                check: $check,
                match: $match,
            );

            $telegram->messages->sendMessage([
                'peer' => $check->telegram_chat_id,

                'reply_to' => [
                    '_' => 'inputReplyToMessage',
                    'reply_to_msg_id' =>
                        $check->telegram_message_id,
                ],

                'message' => $message,
                'parse_mode' => 'html',
                'no_webpage' => true,
            ]);

            $check->update([
                'reported_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Failed to send driver check reply',
                [
                    'check_id' =>
                        $check->id,

                    'chat_id' =>
                        $check->telegram_chat_id,

                    'message_id' =>
                        $check->telegram_message_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        $e::class,
                ],
            );

            throw $e;
        }
    }

    private function buildMessage(
        TelegramDriverCheck $check,
        ?array $match,
    ): string {
        $status =
            $check->status?->value
            ?? 'unknown';

        $lines = [
            '<b>Проверка Telegram водителя</b>',
            '',

            '<b>Статус:</b> '
            . $this->statusLabel($status),

            '<b>ID проверки:</b> '
            . $check->id,

            '<b>ID сообщения:</b> '
            . $check->telegram_message_id,

            '',

            '<b>Телефон:</b>',

            $this->escape(
                $check->phone_normalized
                ?? '-',
            ),

            '',

            '<b>Водитель из сообщения:</b>',

            $this->escape(
                $check->driver_name
                ?? '-',
            ),

            '',

            '<b>Telegram:</b>',

            $this->escape(
                $this->telegramDisplayName(
                    $check,
                ),
            ),
        ];

        /*
         * -------------------------------------------------------------
         * USERNAME
         * -------------------------------------------------------------
         */
        if (
            is_string(
                $check->telegram_username,
            )
            && trim(
                $check->telegram_username,
            ) !== ''
        ) {
            $lines[] =
                '<b>Имя пользователя:</b> @'
                . $this->escape(
                    ltrim(
                        $check->telegram_username,
                        '@',
                    ),
                );
        }

        /*
         * -------------------------------------------------------------
         * TELEGRAM USER ID
         * -------------------------------------------------------------
         */
        if (
            $check->telegram_user_id
        ) {
            $lines[] =
                '<b>ID пользователя:</b> '
                . $check->telegram_user_id;
        }

        /*
         * -------------------------------------------------------------
         * MATCH RESULT
         * -------------------------------------------------------------
         */
        if (
            is_array($match)
        ) {
            $this->appendMatchResult(
                lines: $lines,
                match: $match,
            );
        }

        /*
         * -------------------------------------------------------------
         * ERROR
         * -------------------------------------------------------------
         */
        if (
            is_string(
                $check->error_message,
            )
            && trim(
                $check->error_message,
            ) !== ''
        ) {
            $lines[] = '';

            $lines[] =
                '<b>Ошибка:</b>';

            $lines[] =
                $this->escape(
                    mb_substr(
                        $check->error_message,
                        0,
                        1000,
                    ),
                );
        }

        return implode(
            "\n",
            $lines,
        );
    }

    private function appendMatchResult(
        array &$lines,
        array $match,
    ): void {
        $lines[] = '';

        /*
         * -------------------------------------------------------------
         * SCORE
         * -------------------------------------------------------------
         */
        $score =
            $this->normalizeScore(
                $match['score'] ?? 0,
            );

        $lines[] =
            '<b>Оценка совпадения:</b> '
            . $score;

        /*
         * -------------------------------------------------------------
         * LEVEL
         * -------------------------------------------------------------
         */
        $level =
            (string) (
                $match['level']
                ?? '-'
            );

        $lines[] =
            '<b>Уровень:</b> '
            . $this->levelLabel(
                $level,
            );

        /*
         * -------------------------------------------------------------
         * CONFIDENCE
         * -------------------------------------------------------------
         */
        $confidence =
            $match['confidence']
            ?? null;

        if (
            is_string($confidence)
            && trim($confidence) !== ''
        ) {
            $lines[] =
                '<b>Уверенность:</b> '
                . $this->confidenceLabel(
                    $confidence,
                );
        }

        /*
         * -------------------------------------------------------------
         * MATCHED PARTS
         * -------------------------------------------------------------
         *
         * Показываем только итоговые совпадения.
         *
         * Не используем matched_tokens,
         * потому что там могут быть технические
         * кандидаты и дубликаты.
         */
        $matchedParts =
            $match['matched_parts']
            ?? [];

        if (
            is_array($matchedParts)
            && $matchedParts !== []
        ) {
            $uniqueParts =
                $this->uniqueMatchedParts(
                    $matchedParts,
                );

            if ($uniqueParts !== []) {
                $lines[] = '';

                $lines[] =
                    '<b>Совпавшие части:</b>';

                foreach (
                    $uniqueParts
                    as $part
                ) {
                    $from =
                        trim(
                            (string) (
                                $part['from']
                                ?? $part['actual']
                                ?? ''
                            ),
                        );

                    $to =
                        trim(
                            (string) (
                                $part['to']
                                ?? $part['expected']
                                ?? ''
                            ),
                        );

                    if (
                        $from === ''
                        && $to === ''
                    ) {
                        continue;
                    }

                    $partScore =
                        $this->normalizeScore(
                            $part['score']
                            ?? 0,
                        );

                    $lines[] =
                        '• '
                        . $this->escape(
                            $from,
                        )
                        . ' → '
                        . $this->escape(
                            $to,
                        )
                        . ' ('
                        . $partScore
                        . ')';
                }
            }
        }

        /*
         * -------------------------------------------------------------
         * REASONS
         * -------------------------------------------------------------
         */
        $reasons =
            $match['reasons']
            ?? [];

        if (
            is_array($reasons)
            && $reasons !== []
        ) {
            $labels = [];

            foreach ($reasons as $reason) {
                if (
                    ! is_string($reason)
                    || trim($reason) === ''
                ) {
                    continue;
                }

                $labels[] =
                    $this->reasonLabel(
                        $reason,
                    );
            }

            $labels =
                array_values(
                    array_unique(
                        $labels,
                    ),
                );

            if ($labels !== []) {
                $lines[] = '';

                $lines[] =
                    '<b>Причина:</b>';

                foreach ($labels as $label) {
                    $lines[] =
                        '• '
                        . $this->escape(
                            $label,
                        );
                }
            }
        }
    }

    private function telegramDisplayName(
        TelegramDriverCheck $check,
    ): string {
        $parts = [];

        $firstName =
            trim(
                (string) (
                    $check->telegram_first_name
                    ?? ''
                ),
            );

        $lastName =
            trim(
                (string) (
                    $check->telegram_last_name
                    ?? ''
                ),
            );

        if ($firstName !== '') {
            $parts[] = $firstName;
        }

        if ($lastName !== '') {
            $parts[] = $lastName;
        }

        return $parts !== []
            ? implode(
                ' ',
                $parts,
            )
            : '-';
    }

    private function statusLabel(
        string $status,
    ): string {
        return match ($status) {
            'confirmed' =>
                '✅ ПОДТВЕРЖДЕНО',

            'not_confirmed' =>
                '❌ НЕ ПОДТВЕРЖДЕНО',

            'pending' =>
                '⏳ ОЖИДАЕТ ПРОВЕРКИ',

            'processing' =>
                '🔄 ПРОВЕРЯЕТСЯ',

            default =>
                '❓ НЕИЗВЕСТНЫЙ СТАТУС',
        };
    }

    private function levelLabel(
        string $level,
    ): string {
        return match ($level) {
            'exact' =>
                'точное совпадение',

            'exact_identity' =>
                'очень сильное совпадение',

            'very_high' =>
                'очень высокая',

            'high' =>
                'высокая',

            'confirmed' =>
                'подтверждено',

            'possible' =>
                'возможное совпадение',

            'weak' =>
                'слабое совпадение',

            'low' =>
                'низкое совпадение',

            'none' =>
                'совпадений нет',

            'no_data' =>
                'недостаточно данных',

            default =>
                $level,
        };
    }

    private function confidenceLabel(
        string $confidence,
    ): string {
        return match ($confidence) {
            'high' =>
                'высокая',

            'medium' =>
                'средняя',

            'low' =>
                'низкая',

            'none' =>
                'нет',

            default =>
                $confidence,
        };
    }

    private function reasonLabel(
        string $reason,
    ): string {
        return match ($reason) {
            'identity_match' =>
                'Имя и данные Telegram достаточно хорошо совпадают.',

            'surname_and_first_name_match' =>
                'Совпали фамилия и имя.',

            'surname_and_username_match' =>
                'Совпали фамилия и имя пользователя Telegram.',

            'first_name_and_username_match' =>
                'Совпали имя и имя пользователя Telegram.',

            'strong_first_name_near_surname' =>
                'Имя совпадает уверенно, а фамилия имеет близкое написание.',

            'first_name_only' =>
                'Совпало только имя.',

            'surname_only' =>
                'Совпала только фамилия.',

            'username_only' =>
                'Совпало только имя пользователя Telegram.',

            'missing_name_data' =>
                'Недостаточно данных для проверки имени.',

            'exact_full_name' =>
                'Полное имя совпало.',

            'strong_name_match' =>
                'Обнаружено сильное совпадение имени.',

            'exact_core' =>
                'Основная часть имени совпала полностью.',

            'leet_normalized_exact' =>
                'Имя совпало после нормализации символов.',

            'ordered_subsequence' =>
                'Обнаружено частичное совпадение символов.',

            'ordered_contains' =>
                'Одно имя содержит основную часть другого.',

            'phonetic_equal' =>
                'Имена совпадают по произношению.',

            'fuzzy' =>
                'Обнаружено близкое написание имени.',

            default =>
                $this->humanizeReason(
                    $reason,
                ),
        };
    }

    private function humanizeReason(
        string $reason,
    ): string {
        $reason =
            str_replace(
                [
                    '_',
                    '-',
                ],
                ' ',
                $reason,
            );

        $reason =
            preg_replace(
                '/\s+/u',
                ' ',
                $reason,
            )
            ?? $reason;

        return ucfirst(
            trim($reason),
        );
    }

    private function normalizeScore(
        mixed $score,
    ): string {
        $score =
            is_numeric($score)
                ? (float) $score
                : 0.0;

        $score =
            max(
                0.0,
                min(
                    100.0,
                    $score,
                ),
            );

        if (
            abs(
                $score - round($score),
            ) < 0.00001
        ) {
            return (string) ((int) round($score));
        }

        return rtrim(
            rtrim(
                number_format(
                    $score,
                    2,
                    '.',
                    '',
                ),
                '0',
            ),
            '.',
        );
    }

    private function uniqueMatchedParts(
        array $parts,
    ): array {
        $unique = [];

        $seen = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $field =
                trim(
                    (string) (
                        $part['field']
                        ?? ''
                    ),
                );

            $from =
                trim(
                    (string) (
                        $part['from']
                        ?? $part['actual']
                        ?? ''
                    ),
                );

            $to =
                trim(
                    (string) (
                        $part['to']
                        ?? $part['expected']
                        ?? ''
                    ),
                );

            $key =
                mb_strtolower(
                    implode(
                        '|',
                        [
                            $field,
                            $from,
                            $to,
                        ],
                    ),
                    'UTF-8',
                );

            if (
                isset(
                    $seen[$key],
                )
            ) {
                continue;
            }

            $seen[$key] = true;

            $unique[] = $part;
        }

        return $unique;
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }
}