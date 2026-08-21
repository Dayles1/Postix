<?php

namespace App\Console\Commands\Test;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

class TestLast10Messages extends Command
{
    protected $signature = 'telegram:test2
        {--user-id=49 : User ID whose active phones will be used}
        {--chat=@SpamBot : Chat username}';

    protected $description = 'Get last 10 messages from chat';

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
        $chatUsername = ltrim((string) $this->option('chat'), '@');

        Log::info('telegram:test2 started', [
            'user_id' => $userId,
            'chat' => $chatUsername,
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

            if (blank($phone->session_path) || !file_exists($phone->session_path)) {
                Log::warning('Session file missing', [
                    'user_phone_id' => $phone->id,
                    'session_path' => $phone->session_path,
                ]);
                continue;
            }

            try {
                $madeline = $this->madeline($phone->session_path);
                $madeline->start();

                Log::info('Getting history', [
                    'user_phone_id' => $phone->id,
                    'chat' => $chatUsername,
                ]);

                $history = $madeline->messages->getHistory([
                    'peer' => '@' . $chatUsername,
                    'limit' => 20,
                    'offset_id' => 0,
                    'add_offset' => 0,
                ]);

                Log::info('History keys', [
                    'keys' => array_keys($history),
                ]);

                $messages = $history['messages'] ?? [];

                Log::info('Messages extracted', [
                    'messages_count' => count($messages),
                ]);

                foreach ($messages as $msg) {
                    $sender = ($msg['out'] ?? false)
                        ? 'me'
                        : 'SpamBot';

                    $text = $msg['message'] ?? '';

                    Log::info('Message', [
                        'sender' => $sender,
                        'text' => $text,
                    ]);

                    $this->line("{$sender}: {$text}");
                }

                unset($madeline);
                gc_collect_cycles();
            } catch (\Throwable $e) {
                Log::error('Get history failed', [
                    'user_phone_id' => $phone->id,
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->error("Failed for UserPhone ID {$phone->id}: " . $e->getMessage());
            }
        }

        Log::info('telegram:test2 finished', [
            'user_id' => $userId,
            'chat' => $chatUsername,
        ]);

        return self::SUCCESS;
    }
}