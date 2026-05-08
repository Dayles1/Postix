<?php

namespace App\Http\Controllers\View;

use App\Application\Services\TelegramAuthService;
use App\Http\Controllers\Controller;
use App\Jobs\CompleteLoginJob;
use App\Jobs\RefreshGroupStatusJob;
use App\Models\MessageGroup;
use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TelegramController extends Controller
{
    public function __construct(protected TelegramAuthService $authService) {}

    protected function resolveUserFromRequest(Request $request): User
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                abort(404, 'User topilmadi');
            }
            return $user;
        }
        $user = $request->user();
        if (! $user) {
            abort(401, 'Login talab qilinadi');
        }
        return $user;
    }
    protected function resolveAllUserFromRequest(Request $request): User
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');

        if ($userId) {
            $user = User::withTrashed()->find($userId);

            if (! $user) {
                abort(404, 'User topilmadi');
            }

            return $user;
        }

        $user = $request->user();

        if (! $user) {
            abort(401, 'Login talab qilinadi');
        }

        return $user;
    }


    public function showLoginForm(Request $request)
    {
        $userId = $request->query('user_id');
        return view('telegram.login', compact('userId'));
    }

    public function sendPhone(Request $request)
{
    try {

        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+\d{6,16}$/'],
        ], [
            'phone.required' => __('messages.telegram.phone_required'),
            'phone.regex' => __('messages.telegram.phone_invalid'),
        ]);

        $user = $this->resolveUserFromRequest($request);

        if (!$this->canUsePhone($validated['phone'], $user->id)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.telegram.user_exists')
            ], 403);
        }

        $session = \App\Models\TelegramAuthSession::updateOrCreate(
            [
                'user_id' => $user->id,
                'phone' => $validated['phone'],
            ],
            [
                'status' => 'pending',
                'message_key' => 'wait',
                'message' => null,
                'attempts' => 0
            ]
        );

        $this->authService->login($user, $validated['phone'], $session->id);

        return response()->json([
            'status' => 'processing',
            'message' => __('messages.telegram.sms_sent'),
            'user_id' => $user->id,
            'session_id' => $session->id,
            'message_key' => $session->message_key
        ]);

    } catch (ValidationException $e) {

        return response()->json([
            'status' => 'error',
            'errors' => $e->errors()
        ], 422);

    } catch (\Throwable $e) {

        return response()->json([
            'status' => 'error',
            'message' => __('messages.telegram.server_error')
        ], 500);
    }
}

    public function canUsePhone(string $phone, int $userId): bool
{
    $userPhone = UserPhone::where('phone', $phone)->where('is_active',true)->first();

    if (!$userPhone) {
        return true;
    }
    if ($userPhone->user_id !== $userId) {
        return false;
    }
    if ($userPhone->is_active) {
        return false;
    }
    return true;
}

    public function sendCode(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string',
        ]);

        try {
            $user = $this->resolveUserFromRequest($request);
            $session = TelegramAuthSession::where('user_id', $user->id)->where('phone', $request->phone)->first();

            if (!$session) {
                return response()->json(['status' => 'error', 'message' => 'session_not_found'], 404);
            }

            if (!in_array($session->status, ['sms_sent', 'code_sent'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Not waiting code',
                    'session_status' => $session->status
                ], 409);
            }

            $session->increment('attempts');
            $session->update(['status' => 'processing', 'message' => null, 'message_key' => 'processing', 'last_ping' => now()]);

            $this->authService->completedLogin([
                'user' => $user,
                'phone' => $request->phone,
                'code' => $request->code,
                'sessionId'=>$session->id
            ]);





            return response()->json([
                'status' => 'processing',
                // 'message' => "Telegram Tasdiqlash jarayoni boshlandi {$request->phone}!",
                // 'redirect' => $redirect,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function completeLogin(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'phone'    => 'required|string|min:9|max:20',
            'password' => 'nullable|string|min:4|max:100',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $r->phone;
        $password = $r->password;

        $session = TelegramAuthSession::where('phone', $phone)->where('user_id', $r->user_id)->first();

        if (!$session) {
            return response()->json(['status' => 'error', 'message' => 'session_not_found'], 404);
        }

        // allow password only if we are in need_password
        if ($session->status !== 'need_password') {
            return response()->json([
                'status' => 'error',
                'message' => 'not_waiting_password',
                'session_status' => $session->status
            ], 409);
        }

        $session->update(['status' => 'processing', 'last_ping' => now(), 'message_key' => 'processing', 'message' => null]);

        CompleteLoginJob::dispatch($phone, $r->user_id, $session->id, $password)->onQueue('telegram');

        return response()->json([
            'status' => 'processing',
            'session_id' => $session->id,
            'message_key' => 'processing'
        ]);
    }
 
    // public function cancel(MessageGroup $group): RedirectResponse
    // {
    //     if (in_array($group->status, ['canceled'])) {
    //         return redirect()
    //             ->back()
    //             ->with('error', __('messages.group.cannot_cancel', ['id' => $group->id]));
    //     }
    //     $group->update(['status' => 'canceled']);

    //     CleanupScheduledJob::dispatch($group->id)
    //         ->onQueue('telegram');

    //     return redirect()
    //         ->back()
    //         ->with('success', __('messages.group.canceled', ['id' => $group->id]));
    // }
    public function cancel(MessageGroup $group): RedirectResponse
    {
        if (in_array($group->status, ['canceled'])) {
            return redirect()
                ->back()
                ->with('error', __('messages.group.cannot_cancel', ['id' => $group->id]));
        }
        $group->update(['status' => 'canceled']);
    
        $group->messages()->where('status', 'pending')->update(['status' => 'canceled']);

        return redirect()
            ->back()
            ->with('success', __('messages.group.canceled', ['id' => $group->id]));
    }
    /**
     * Operatsiyani yangilash (REFRESH)
     */
    public function refresh(MessageGroup $group): RedirectResponse
    {
        RefreshGroupStatusJob::dispatch($group->id)
            ->onQueue('telegram');

        return redirect()
            ->back()
            ->with('success', "Operatsiya #{$group->id} yangilash jarayoniga yuborildi.");
    }
    public function logout(Request $request): RedirectResponse
    {
        $user = $this->resolveAllUserFromRequest($request);

        $this->authService->logout($user, $request->input('phone'));
        
        // sleep(3);
        return redirect()
            ->back()
            ->with('success', 'Siz muvaffaqiyatli Telegramdan chiqdingiz.');
    }

    public function status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:9|max:20',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'validation_error', 'errors' => $validator->errors()], 422);
        }

        $session = TelegramAuthSession::where('phone', $request->phone)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $session->update(['last_ping' => now()]);

        return response()->json([
            'status' => $session->status,
            'message' => $session->message,
            'message_key' => $session->message_key
        ]);
    }
}
