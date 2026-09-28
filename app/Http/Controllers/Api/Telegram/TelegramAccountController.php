<?php

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Exceptions\TelegramAccountStateException;
use App\Application\Telegram\Services\TelegramAccountSessionManager;
use App\Http\Controllers\Controller;
use App\Jobs\Telegram\AuthTelegramAccountJob;
use App\Jobs\Telegram\LogoutTelegramAccountJob;
use App\Jobs\Telegram\VerifyTelegramAccountCodeJob;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use Illuminate\Http\Request;

class TelegramAccountController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => TelegramAccount::query()
                ->with([
                    'processes' => function ($query) {
                        // можно добавить условия, сортировку и т.д.
                        $query->orderBy('created_at', 'desc');
                    }
                ])
                ->get(),
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

    /**
     * 2FA password, the step after verify-code answered need_password.
     */
    public function password(Request $request, TelegramAccountSessionManager $sessions)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'max:256'],
        ]);

        $account = TelegramAccount::query()
            ->where('phone', TelegramAccount::normalizePhone($request->phone))
            ->firstOrFail();

        try {
            $sessions->submitPassword($account, $request->password);
        } catch (TelegramAccountStateException $e) {
            return response()->json([
                'message' => $e->translated(),
                'status' => $account->status,
            ], 409);
        }

        return response()->json([
            'message' => '2FA job dispatched.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        /*
         * The job takes the account id; it used to be handed the phone.
         */
        $account = TelegramAccount::query()
            ->where('phone', TelegramAccount::normalizePhone($request->phone))
            ->firstOrFail();

        LogoutTelegramAccountJob::dispatch(
            $account->id
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

    public function manageFailures(Request $request)
{
    $request->validate([
        'ids' => ['required', 'array', 'min:1'],
        'ids.*' => ['required', 'integer'],
        'action' => ['required', 'in:restart,set'],
    ]);

    $failures = $request->action === 'restart' ? 0 : 7;

    $updated = TelegramAccountProcess::query()
        ->whereIn('telegram_account_id', $request->ids)
        ->update([
            'failures' => $failures,
            // 'is_available' => true,
        ]);

    return response()->json([
        'message' => 'Failures updated successfully.',
        'action' => $request->action,
        'failures' => $failures,
        'updated' => $updated,
    ]);
}
}