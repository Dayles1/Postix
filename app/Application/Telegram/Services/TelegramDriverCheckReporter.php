<?php

namespace App\Application\Telegram\Services;

use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramDriverCheckReporter
{
    public function send(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $check,
        ?array $match = null,
    ): void {
        try {
            $message = $this->buildMessage(
                $check,
                $match
            );

            $telegram->messages->sendMessage([
                'peer' => $check->telegram_chat_id,

                'reply_to' => [
                    '_' => 'inputReplyToMessage',
                    'reply_to_msg_id' => $check->telegram_message_id,
                ],

                'message' => $message,
                'parse_mode' => 'html',
                'no_webpage' => true,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Failed to send driver check reply',
                [
                    'check_id' => $check->id,
                    'chat_id' =>
                        $check->telegram_chat_id,

                    'message_id' =>
                        $check->telegram_message_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    private function buildMessage(
        TelegramDriverCheck $check,
        ?array $match,
    ): string {
        $status = $check->status?->value ?? 'unknown';
        $reason = $check->reason?->value;

        $statusLabel = match ($status) {
            'confirmed' => '✅ CONFIRMED',
            'not_confirmed' => '❌ NOT CONFIRMED',
            'pending' => '⏳ PENDING',
            default => '❓ UNKNOWN',
        };

        $lines = [
            '<b>Driver Telegram Check</b>',
            '',
            "<b>Status:</b> {$statusLabel}",
            "<b>Check ID:</b> {$check->id}",
            "<b>Message ID:</b> {$check->telegram_message_id}",
            '',
            '<b>Phone:</b>',
            $this->escape($check->phone_normalized ?? '-'),
            '',
            '<b>Driver from message:</b>',
            $this->escape($check->driver_name ?? '-'),
            '',
            '<b>Telegram:</b>',
            $this->escape(
                trim(
                    implode(' ', array_filter([
                        $check->telegram_first_name,
                        $check->telegram_last_name,
                    ]))
                ) ?: '-'
            ),
        ];

        if ($check->telegram_username) {
            $lines[] = '<b>Username:</b> @'
                .$this->escape($check->telegram_username);
        }

        if ($check->telegram_user_id) {
            $lines[] = '<b>User ID:</b> '
                .$check->telegram_user_id;
        }

        if ($match) {
            $lines[] = '';
            $lines[] = '<b>Match score:</b> '
                .($match['score'] ?? 0);

            $lines[] = '<b>Level:</b> '
                .$this->escape($match['level'] ?? '-');

            $matchedTokens = $match['matched_tokens'] ?? [];

            if ($matchedTokens !== []) {
                $lines[] = '';
                $lines[] = '<b>Matched parts:</b>';

                foreach ($matchedTokens as $token) {
                    $lines[] =
                        '• '
                        .$this->escape($token['expected'] ?? '')
                        .' → '
                        .$this->escape($token['actual'] ?? '')
                        .' ('
                        .($token['score'] ?? 0)
                        .')';
                }
            }
        }

        if ($reason) {
            $lines[] = '';
            $lines[] = '<b>Reason:</b> '
                .$this->escape($reason);
        }

        if ($check->error_message) {
            $lines[] = '';
            $lines[] = '<b>Error:</b>';
            $lines[] = $this->escape(
                mb_substr(
                    $check->error_message,
                    0,
                    1000
                )
            );
        }

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}