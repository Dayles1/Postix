<?php

namespace App\Http\Controllers\Api\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\AuthTelegramAccountJob;
use App\Jobs\Telegram\LogoutTelegramAccountJob;
use App\Jobs\Telegram\VerifyTelegramAccountCodeJob;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Http\Request;

class TelegramAccountController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => TelegramAccount::query()->get(),
        ]);
    }

    public function auth(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = preg_replace('/\s+/', '', trim($request->phone));

        $alreadyAuthorized = TelegramAccount::query()
            ->where('phone', $phone)
            ->where('is_authorized', true)
            ->exists();

        if ($alreadyAuthorized) {
            return response()->json([
                'message' => 'This Telegram account is already authorized.',
            ], 409);
        }

        AuthTelegramAccountJob::dispatch($phone)
            ->onQueue('telegram');

        return response()->json([
            'message' => 'Authentication job dispatched.',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        $phone = preg_replace('/\s+/', '', trim($request->phone));
        VerifyTelegramAccountCodeJob::dispatch(
            $phone,
            $request->code
        )->onQueue('telegram');

        return response()->json([
            'message' => 'Verification job dispatched.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        LogoutTelegramAccountJob::dispatch(
            $request->phone
        )->onQueue('telegram');

        return response()->json([
            'message' => 'Logout job dispatched.',
        ]);
    }

    public function show(string $id)
    {
        $account = TelegramAccount::findOrFail($id);

        return response()->json([
            'data' => $account,
        ]);
    }
}