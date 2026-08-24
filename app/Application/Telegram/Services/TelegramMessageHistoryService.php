<?php

namespace App\Application\Telegram\Services;

use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramMessageHistoryService
{
    /**
     * Get chat history page by page.
     *
     * Messages are returned by Telegram from newest to oldest.
     *
     * @return \Generator<int, array>
     */
    public function messages(
        API $api,
        string|int $peer,
        int $limit = 100,
    ): \Generator {
        $offsetId = 0;

        while (true) {
            try {
                Log::debug('Telegram history request', [
                    'peer' => $peer,
                    'offset_id' => $offsetId,
                    'limit' => $limit,
                ]);

                $result = $api->messages->getHistory(
                    peer: $peer,
                    offset_id: $offsetId,
                    limit: $limit,
                );
            } catch (Throwable $e) {
                Log::error('Telegram history request failed', [
                    'peer' => $peer,
                    'offset_id' => $offsetId,
                    'limit' => $limit,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }

            $messages = $result['messages'] ?? [];

            if ($messages === []) {
                Log::info('Telegram history finished', [
                    'peer' => $peer,
                ]);

                break;
            }

            Log::debug('Telegram history page received', [
                'peer' => $peer,
                'count' => count($messages),
                'offset_id' => $offsetId,
            ]);

            foreach ($messages as $message) {
                yield $message;
            }

            /*
             * Telegram returns messages from newest to oldest.
             *
             * Use the smallest message ID from the current page
             * as the offset for the next request.
             */
            $messageIds = array_filter(
                array_map(
                    static fn (array $message): int => (int) ($message['id'] ?? 0),
                    $messages
                )
            );

            if ($messageIds === []) {
                break;
            }

            $newOffsetId = min($messageIds);

            /*
             * Protection against infinite loops.
             */
            if ($newOffsetId <= $offsetId && $offsetId !== 0) {
                Log::warning('Telegram history pagination stopped because offset did not advance', [
                    'peer' => $peer,
                    'offset_id' => $offsetId,
                    'new_offset_id' => $newOffsetId,
                ]);

                break;
            }

            $offsetId = $newOffsetId;
        }
    }
}