<?php

namespace App\Console\Commands;

use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Models\UserPhone;
use danog\MadelineProto\API;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteLoginCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     *
     * Usage:
     *  php artisan app:complete-login {phone} {userId} {sessionId} {password?}
     */
    protected $signature = 'app:complete-login 
                            {phone : phone number used for session} 
                            {userId : local user id} 
                            {sessionId : telegram_auth_sessions id} 
                            {password? : 2FA password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete Telegram 2FA login for a saved session (converted from CompleteLoginJob).';

    /**
     * Create MadelineProto API instance for given phone/user.
     */
    protected function madeline(string $phone, int $userId): API
    {
        $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");
        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0775, true);
        }
        return new API($sessionPath);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $phone = (string) $this->argument('phone');
        $userId = (int) $this->argument('userId');
        $sessionId = (int) $this->argument('sessionId');
        $password = $this->argument('password');

        $session = TelegramAuthSession::find($sessionId);

        if (!$session) {
            $this->error("CompleteLoginCommand: session not found {$sessionId}");
            Log::error("CompleteLoginCommand: session not found {$sessionId}");
            return Command::FAILURE;
        }

        if (!$password) {
            $session->update([
                'status' => 'need_password',
                'message_key' => '2fa_password_required',
                'message' => null,
            ]);

            $this->info("Session {$sessionId} requires 2FA password. Marked as need_password.");
            return Command::SUCCESS;
        }

        $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");

        if (!file_exists($sessionPath) && !is_dir($sessionPath)) {
            $session->update([
                'status' => 'failed',
                'message_key' => 'session_not_found',
                'message' => null,
            ]);

            $this->error("CompleteLoginCommand: session file not found at {$sessionPath}");
            Log::error("CompleteLoginCommand: session file not found at {$sessionPath}");
            return Command::FAILURE;
        }

        $Madeline = $this->madeline($phone, $userId);

        try {
            $this->info("CompleteLoginCommand: attempting complete2falogin for {$phone}");
            Log::info("CompleteLoginCommand: complete2falogin {$phone}");

            $authorization = $Madeline->complete2falogin($password);

            if (isset($authorization['_']) && $authorization['_'] === 'account.needSignup') {
                throw new \Exception('ACCOUNT_NOT_REGISTERED');
            }
            $self = $Madeline->getSelf();
            $tgId=$self['id'];
            User::where('id', $userId)
                ->whereNull('telegram_id')
                ->update(['telegram_id' => $tgId]);

            UserPhone::updateOrCreate(
                ['user_id' => $userId, 'phone' => $phone],
                [
                    'telegram_user_id' => $tgId,
                    'session_path' => $sessionPath,
                    'is_active'         => true,

                ]
            );
            
            $session->update([
                'status' => 'success',
                'telegram_user_id' => $tgId,
                'session_path' => $sessionPath,
                'message_key' => 'telegram_verified',
                'message' => null,
            ]);

            $this->info("CompleteLoginCommand: success for {$phone}, telegram_user_id={$self['id']}");
            Log::info("CompleteLoginCommand: success for {$phone}, telegram_user_id={$self['id']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error("CompleteLoginCommand error {$phone}: {$e->getMessage()}");

            $session->update([
                'status' => 'failed',
                'message_key' => null,
                'message' => $e->getMessage(),
            ]);

            $this->error("CompleteLoginCommand error for {$phone}: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}