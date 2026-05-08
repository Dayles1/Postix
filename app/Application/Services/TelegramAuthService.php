<?php

namespace App\Application\Services;

use App\Jobs\Delete\TelegramLogoutJob;
use App\Jobs\TelegramAuthJob;
use App\Jobs\TelegramVerifyJob;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Support\Facades\Auth;

class TelegramAuthService
{
    public function login(User $user, string $phone,int $sessionId)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        TelegramAuthJob::dispatch($phone, $user->id, $sessionId)->onQueue('telegram');
    }

    public function completedLogin(array $data)
    {
        $user = $data['user'];
        $phone = $data['phone'];
        $code = $data['code'];
        $password = $data['password'] ?? null;
        $sessionId=$data['sessionId'];
        TelegramVerifyJob::dispatch($phone, $user->id, $code, $sessionId)
            ->onQueue('telegram');
    }

    public function logout(User $user, string $phone): UserPhone
    {   
        $userPhone = UserPhone::where('user_id', $user->id)
            ->where('phone', $phone)
            ->first();
        
        $userPhone->state = 'logging_out';
        $userPhone->save();
        AuditLog::create([
            'type' => $user->department?->plan,
            'action' => 'phone_logout',
            'subject_type' => UserPhone::class,
            'subject_id' => $userPhone->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'changes' => [
                'phone' => [
                    'old' => [
                        'phone' => $userPhone->phone,
                        'is_active' => true,
                    ],
                    'new' => [
                        'phone' => $userPhone->phone,
                        'is_active' => false,
                    ],
                ],
            ],
        ]);
        TelegramLogoutJob::dispatch($userPhone->id)
            ->onQueue('telegram');

        return $userPhone;
    }
}
