<?php

namespace App\Console\Commands\Test;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

class Test extends Command
{
    protected $signature = 'telegram:test0
        {--user-id=49 : User ID whose active phones will be used}
        {--bot=@SpamBot : Bot username}
    ';

    protected $description = 'Mute bot forever and send /start';

    protected function madeline(string $sessionPath): API
    {
        $settings = new \danog\MadelineProto\Settings();

        $appInfo = new AppInfo();
        $apiId = (int) env('TELEGRAM_API_ID', 0);
        $apiHash = env('TELEGRAM_API_HASH', '');

        if (!$apiId || !$apiHash) {
            Log::warning('TELEGRAM_API_ID or TELEGRAM_API_HASH not set');
        }

        $appInfo->setApiId($apiId);
        $appInfo->setApiHash($apiHash);
        $settings->setAppInfo($appInfo);

        $loggerSettings = (new LoggerSettings())->setType(Logger::ERROR);
        $settings->setLogger($loggerSettings);

        return new API($sessionPath, $settings);
    }

    public function handle(): int
    {
        $userId = (int) $this->option('user-id');
        $botUsername = ltrim((string) $this->option('bot'), '@');
        $muteUntil = now()->addDay()->timestamp;

        Log::info('telegram:mute-and-start-bot started', [
            'user_id' => $userId,
            'bot' => $botUsername,
            'mute_until' => $muteUntil,
        ]);

        $phones = UserPhone::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        Log::info('Active phones found', [
            'count' => $phones->count(),
            'phone_ids' => $phones->pluck('id')->all(),
        ]);

        if ($phones->isEmpty()) {
            $this->warn("No active phones found for user ID {$userId}");
            return self::SUCCESS;
        }

        foreach ($phones as $phone) {
            Log::info('Processing phone', [
                'user_phone_id' => $phone->id,
                'session_path' => $phone->session_path,
            ]);

            if (blank($phone->session_path) || ! file_exists($phone->session_path)) {
                Log::warning('Session file missing', [
                    'user_phone_id' => $phone->id,
                    'session_path' => $phone->session_path,
                ]);
                $this->warn("Session file missing for UserPhone ID {$phone->id}");
                continue;
            }

            try {
                $madeline = $this->madeline($phone->session_path);

                Log::info('Starting Madeline', [
                    'user_phone_id' => $phone->id,
                ]);

                $madeline->start();

                Log::info('Muting bot forever', [
                    'user_phone_id' => $phone->id,
                    'bot' => $botUsername,
                ]);

                $peer = [
                    '_' => 'inputNotifyPeer',
                    'peer' => '@' . $botUsername,
                ];

                $madeline->account->updateNotifySettings(
                    peer: $peer,
                    settings: [
                        '_' => 'inputPeerNotifySettings',
                        'silent' => true,
                        'mute_until' => time() + 86400,
                    ]
                );

                Log::info('Sending /start to bot', [
                    'user_phone_id' => $phone->id,
                    'bot' => $botUsername,
                ]);

                $madeline->messages->sendMessage(
                    peer: '@' . $botUsername,
                    message: '/start'
                );

                Log::info('Done for phone', [
                    'user_phone_id' => $phone->id,
                ]);

                $this->info("Muted forever and sent /start for UserPhone ID {$phone->id}");

                unset($madeline);
                gc_collect_cycles();
            } catch (\Throwable $e) {
                Log::error('Failed processing phone', [
                    'user_phone_id' => $phone->id,
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("Failed for UserPhone ID {$phone->id}: ".$e->getMessage());
            }
        }

        Log::info('telegram:mute-and-start-bot finished', [
            'user_id' => $userId,
            'bot' => $botUsername,
        ]);

        return self::SUCCESS;
    }
}