<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads a CRM penalty message:
 *
 *   🆘 Повторное отправление штрафа №3 по запросу #TLS04834
 *   Статус: В поиске перевозчика
 *   Время на статус: 2 ч
 *   В статусе с: 02.10.2026 11:16
 *   Стоит в статусе: 5 ч 8 мин
 *   Тип транспорта: Тентованный прицеп
 *   Кубатура: 96 м³
 *   Ответственный (Operation): PULATOV AFZAL AHMADJON O'G'LI
 *
 *   PULATOV AFZAL AHMADJON O'G'LI : 331 / 54          (or "Групп / сообщений в Telegram: 0 / 0")
 *
 *   Открыть запрос (https://crm…)
 *
 * The first penalty ("⚠️ Штраф по запросу #…") has repeat number 1. Every
 * field is optional: a line the bot drops or renames leaves its key null.
 */
final class TelegramPenaltyMessageParser
{
    /**
     * What the bot writes when nobody is responsible.
     */
    private const EMPTY_VALUES = ['', '—', '–', '-'];

    /**
     * @return array{
     *     request_number: string|null,
     *     repeat_number: int,
     *     crm_status: string|null,
     *     status_limit: string|null,
     *     status_since: string|null,
     *     time_in_status: string|null,
     *     transport_type: string|null,
     *     volume: string|null,
     *     responsible_role: string|null,
     *     responsible_name: string|null,
     *     posted_by_name: string|null,
     *     telegram_groups: int|null,
     *     telegram_messages: int|null,
     *     crm_url: string|null,
     * }
     */
    public function parse(string $text): array
    {
        $request = null;
        $repeat = 1;

        if (preg_match('/штрафа\s*№\s*(\d+)\s*по запросу\s*#\s*([A-Za-z0-9_-]+)/iu', $text, $m) === 1) {
            $repeat = max(1, (int) $m[1]);
            $request = $m[2];
        } elseif (preg_match('/Штраф по запросу\s*#\s*([A-Za-z0-9_-]+)/iu', $text, $m) === 1) {
            $request = $m[1];
        }

        [$role, $name] = $this->responsible($text);

        [$postedBy, $groups, $messages] = $this->telegramActivity($text);

        return [
            'request_number' => $request,
            'repeat_number' => $repeat,
            'crm_status' => $this->line($text, 'Статус'),
            'status_limit' => $this->line($text, 'Время на статус'),
            'status_since' => $this->date($this->line($text, 'В статусе с')),
            'time_in_status' => $this->line($text, 'Стоит в статусе'),
            'transport_type' => $this->line($text, 'Тип транспорта'),
            'volume' => $this->line($text, 'Кубатура'),
            'responsible_role' => $role,
            'responsible_name' => $name,
            'posted_by_name' => $postedBy,
            'telegram_groups' => $groups,
            'telegram_messages' => $messages,
            'crm_url' => preg_match('~https?://\S+?(?=\)|\s|$)~u', $text, $m) === 1
                ? $m[0]
                : null,
        ];
    }

    /**
     * "Ответственный (Operation): ФИО" / "Ответственный (Sales): ФИО".
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function responsible(string $text): array
    {
        if (preg_match('/(?:^|\R)\s*Ответственный\s*\(\s*([^)]+?)\s*\)\s*:[ \t]*([^\r\n]*)/iu', $text, $m) !== 1) {
            return [null, null];
        }

        $role = match (mb_strtolower(trim($m[1]))) {
            'operation', 'operations' => OperationUser::ROLE_OPERATION,
            'sales' => OperationUser::ROLE_SALES,
            default => null,
        };

        return [$role, $this->value($m[2])];
    }

    /**
     * "ФИО: 331 / 54" - who posted the request to Telegram and how widely -
     * or "Групп / сообщений в Telegram: 0 / 0" when nobody did.
     *
     * @return array{0: string|null, 1: int|null, 2: int|null}
     */
    private function telegramActivity(string $text): array
    {
        if (preg_match('/(?:^|\R)[ \t]*([^\r\n:]+?)[ \t]*:[ \t]*(\d+)[ \t]*\/[ \t]*(\d+)[ \t]*(?=\R|$)/u', $text, $m) !== 1) {
            return [null, null, null];
        }

        $name = trim($m[1]);

        if (mb_stripos($name, 'Групп') === 0) {
            $name = null;
        }

        return [$name, (int) $m[2], (int) $m[3]];
    }

    private function line(string $text, string $label): ?string
    {
        $pattern = '/(?:^|\R)[ \t]*' . preg_quote($label, '/') . '[ \t]*:[ \t]*([^\r\n]*)/u';

        if (preg_match($pattern, $text, $m) !== 1) {
            return null;
        }

        return $this->value($m[1]);
    }

    private function value(string $value): ?string
    {
        $value = trim($value);

        return in_array($value, self::EMPTY_VALUES, true)
            ? null
            : $value;
    }

    /**
     * "02.10.2026 11:16" in the app timezone, as Y-m-d H:i:s.
     */
    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y H:i', $value)
                ->startOfMinute()
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
