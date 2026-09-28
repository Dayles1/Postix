<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Exceptions\TelegramAccountStateException;
use App\Application\Telegram\Queries\ListTelegramSessions;
use App\Application\Telegram\Services\TelegramAccountSessionManager;
use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\TelegramSessionIndexRequest;
use App\Http\Resources\Telegram\TelegramSessionResource;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Behind the Telegram sessions page: every MadelineProto account the
 * driver check (and the resolver pool) runs on.
 *
 * Nothing here opens a session - MadelineProto only works on the CLI.
 * Each action moves the account into an in-flight status and queues the
 * artisan command that does the work; the page polls show() until the
 * command has written its result.
 *
 * Access is enforced by the `role:driverCheck,superadmin` middleware on the
 * route group (see routes/web.php), matching the rest of the driver-check
 * panel.
 */
final class TelegramSessionController extends Controller
{
    public function __construct(
        private readonly TelegramAccountSessionManager $sessions,
    ) {}

    public function index(
        TelegramSessionIndexRequest $request,
        ListTelegramSessions $query,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return TelegramSessionResource::collection(
            $query->execute($filters),
        )->additional([
            'stats' => $query->stats($filters),
        ]);
    }

    public function show(TelegramAccount $account): TelegramSessionResource
    {
        return new TelegramSessionResource(
            $account->load('processes'),
        );
    }

    /**
     * Starts a login: Telegram sends the code to the phone.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[\d\s()-]{7,20}$/'],
        ], [
            'phone.regex' => __('telegram.sessions.validation.phone'),
        ]);

        return $this->attempt(
            fn () => $this->sessions->start($validated['phone']),
            201,
        );
    }

    public function code(Request $request, TelegramAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[\d\s-]{4,12}$/'],
        ], [
            'code.regex' => __('telegram.sessions.validation.code'),
        ]);

        return $this->attempt(
            fn () => $this->sessions->submitCode($account, $validated['code']),
        );
    }

    public function password(Request $request, TelegramAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:256'],
        ]);

        return $this->attempt(
            fn () => $this->sessions->submitPassword($account, $validated['password']),
        );
    }

    public function check(TelegramAccount $account): JsonResponse
    {
        return $this->attempt(
            fn () => $this->sessions->check($account),
        );
    }

    public function logout(TelegramAccount $account): JsonResponse
    {
        return $this->attempt(
            fn () => $this->sessions->logout($account),
        );
    }

    public function destroy(TelegramAccount $account): JsonResponse
    {
        try {
            $this->sessions->delete($account);
        } catch (TelegramAccountStateException $e) {
            return $this->conflict($e);
        }

        return response()->json([
            'message' => __('telegram.sessions.messages.deleted'),
        ]);
    }

    /**
     * Switches one process of the account on or off. Switching on also
     * clears the failure streak and a busy flag a crashed worker left.
     */
    public function process(
        Request $request,
        TelegramAccount $account,
        string $process,
    ): TelegramSessionResource {
        $processEnum = TelegramAccountProcessEnum::tryFrom($process);

        abort_if($processEnum === null, 404);

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $state = TelegramAccountProcess::query()->firstOrCreate([
            'telegram_account_id' => $account->id,
            'process' => $processEnum->value,
        ]);

        $validated['is_available']
            ? $state->enable()
            : $state->disable(__('telegram.sessions.processes.disabled_manually'));

        return new TelegramSessionResource(
            $account->load('processes'),
        );
    }

    /**
     * @param Closure(): TelegramAccount $action
     */
    private function attempt(Closure $action, int $status = 200): JsonResponse
    {
        try {
            $account = $action();
        } catch (TelegramAccountStateException $e) {
            return $this->conflict($e);
        }

        return (new TelegramSessionResource(
            $account->load('processes'),
        ))
            ->response()
            ->setStatusCode($status);
    }

    private function conflict(TelegramAccountStateException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->translated(),
            'reason' => $e->reason,
        ], 409);
    }
}
