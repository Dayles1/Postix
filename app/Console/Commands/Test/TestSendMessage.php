<?php

namespace App\Console\Commands\Test;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

class TestSendMessage extends Command
{
    protected $signature = 'telegram:test
        {--user-id=49 : User ID whose active phones will be used}
        {--bot=@SpamBot : Bot username}';

    protected $description = 'Send a message to bot';

    protected function madeline(string $sessionPath): API
    {
        $settings = new \danog\MadelineProto\Settings();

        $appInfo = new AppInfo();
        $apiId = (int) env('TELEGRAM_API_ID', 0);
        $apiHash = env('TELEGRAM_API_HASH', '');

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

        $message = "Salom bot\nBu message code ichidan yuborildi.";

        $phones = UserPhone::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        foreach ($phones as $phone) {
            if (blank($phone->session_path) || ! file_exists($phone->session_path)) {
                Log::warning('Session file missing', [
                    'user_phone_id' => $phone->id,
                    'session_path' => $phone->session_path,
                ]);
                continue;
            }

            try {
                $madeline = $this->madeline($phone->session_path);
                $madeline->start();

                Log::info('Sending message', [
                    'user_phone_id' => $phone->id,
                    'bot' => $botUsername,
                    'message' => $message,
                ]);

                $madeline->messages->sendMessage(
                    peer: '@' . $botUsername,
                    message: $message
                );

                $this->info("Sent message for UserPhone ID {$phone->id}");
            } catch (\Throwable $e) {
                Log::error('Send message failed', [
                    'user_phone_id' => $phone->id,
                    'message' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]);

                $this->error("Failed for UserPhone ID {$phone->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}