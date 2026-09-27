<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use Illuminate\Console\Command;
use Telegram\Bot\BotsManager;
use Throwable;

/**
 * Read-only look at the bot's webhook.
 *
 * The webhook does not belong to this project: Telegram calls our
 * gateway API, and the gateway forwards the updates to
 * POST /api/telegram/webhook here. Setting or deleting the webhook from
 * postix takes it away from the gateway - an earlier version of this
 * command did exactly that - so it only shows what Telegram has.
 */
final class TelegramBotWebhookCommand extends Command
{
    protected $signature = 'telegram:bot-webhook
        {action=info : only "info"; the gateway project owns the webhook}';

    protected $description =
        'Show the bot webhook Telegram has on file (read-only; the gateway owns it)';

    public function handle(): int
    {
        if ($this->argument('action') !== 'info') {
            $this->error(
                'The webhook belongs to the gateway project: set or delete it there. '
                . 'Changing it from postix would cut the gateway off.',
            );

            return self::FAILURE;
        }

        try {
            $info = app(BotsManager::class)->bot()->getWebhookInfo();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode(
            $info->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return self::SUCCESS;
    }
}
