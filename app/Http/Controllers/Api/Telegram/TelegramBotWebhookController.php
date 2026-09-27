<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Actions\HandleDriverCheckBotCallback;
use App\Application\Telegram\Services\DriverCheckBot;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram updates, as forwarded by the gateway project.
 *
 * Telegram -> gateway API (owns the webhook) -> here. The body is the
 * Telegram Update, unchanged. Handled: the driver check buttons, and a
 * greeting for anyone who writes to the bot directly; the rest is
 * acknowledged and ignored.
 */
final class TelegramBotWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        HandleDriverCheckBotCallback $callbacks,
        DriverCheckBot $bot,
    ): JsonResponse {
        // if (! $this->authorized($request)) {
        //     return response()->json(['ok' => false], 403);
        // }
        Log::info('Driver check bot webhook received', [
            'body' => $request->all(),
        ]);
        $callback = $request->input('callback_query');

        if (is_array($callback)) {
            try {
                $callbacks->execute($callback);
            } catch (Throwable $e) {
                /*
                 * Always 200: a failing update answered with an error is
                 * redelivered by Telegram, over and over.
                 */
                Log::error(
                    'Driver check bot callback failed',
                    [
                        'data' => $callback['data'] ?? null,
                        'error' => $e->getMessage(),
                        'exception' => $e::class,
                    ],
                );
            }
        }

        $message = $request->input('message');

        /*
         * Private chats only: in a group the bot would answer every
         * message it is allowed to see.
         */
        if (is_array($message) && data_get($message, 'chat.type') === 'private') {
            try {
                $bot->greet(
                    (int) data_get($message, 'chat.id'),
                    (string) data_get($message, 'from.first_name', ''),
                );
            } catch (Throwable $e) {
                Log::warning(
                    'Driver check bot could not answer a private message',
                    ['error' => $e->getMessage()],
                );
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The shared secret, in whichever header the gateway sends it: the
     * one Telegram itself uses, passed through as is, or a bearer token.
     */
    private function authorized(Request $request): bool
    {
        $secret = (string) config('services.telegram.bot_webhook_secret', '');

        if ($secret === '') {
            return true;
        }

        foreach ([
            (string) $request->header('X-Telegram-Bot-Api-Secret-Token'),
            (string) $request->bearerToken(),
        ] as $candidate) {
            if ($candidate !== '' && hash_equals($secret, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
