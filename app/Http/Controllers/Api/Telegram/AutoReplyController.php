<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\AutoReplyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Behind the auto replies page: the one JSON file the listener answers
 * private messages from (config/auto_replies.php).
 *
 * The listener reads the file on every message, so a save applies from the
 * next one on. Access is enforced by the `role:driverCheck,superadmin`
 * middleware on the route group (see routes/web.php).
 */
final class AutoReplyController extends Controller
{
    public function __construct(
        private readonly AutoReplyStore $store,
    ) {
    }

    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function update(AutoReplyRequest $request): JsonResponse
    {
        $this->store->save(
            AutoReplyRules::fromArray($request->validated()),
        );

        return $this->respond(__('telegram.auto_replies.messages.saved'));
    }

    /**
     * The file goes; the config's defaults are back.
     */
    public function destroy(): JsonResponse
    {
        $this->store->reset();

        return $this->respond(__('telegram.auto_replies.messages.reset'));
    }

    /**
     * The file as the listener reads it - the defaults when there is none.
     */
    public function download(): Response
    {
        $json = json_encode(
            $this->store->current()->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return response($json . "\n", 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . basename($this->store->path()) . '"',
        ]);
    }

    private function respond(?string $message = null): JsonResponse
    {
        return response()->json([
            'data' => $this->store->current()->toArray(),
            'defaults' => $this->store->defaults()->toArray(),
            'customised' => $this->store->exists(),
            /*
             * A hand edit gone wrong: the listener answers nothing until it
             * is fixed or saved over from here.
             */
            'file_error' => $this->store->error(),
            'path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->store->path()),
            ...($message !== null ? ['message' => $message] : []),
        ]);
    }
}
