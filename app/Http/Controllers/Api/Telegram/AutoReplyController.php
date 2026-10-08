<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\AutoReplyMedia;
use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\AutoReplyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        private readonly AutoReplyMedia $media,
    ) {
    }

    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function update(AutoReplyRequest $request): JsonResponse
    {
        $rules = AutoReplyRules::fromArray($request->validated());

        $this->store->save($rules);

        $this->media->prune($rules->mediaFiles());

        return $this->respond(__('telegram.auto_replies.messages.saved'));
    }

    /**
     * The file goes; the config's defaults are back.
     */
    public function destroy(): JsonResponse
    {
        $this->store->reset();

        $this->media->prune($this->store->current()->mediaFiles());

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

    /**
     * A GIF or a voice message, stored until a save puts it into the
     * rules (or a day passes without one).
     */
    public function upload(Request $request): JsonResponse
    {
        $type = $request->input('type') === AutoReplyRules::MEDIA_VOICE
            ? AutoReplyRules::MEDIA_VOICE
            : AutoReplyRules::MEDIA_GIF;

        $request->validate([
            'type' => ['required', 'in:' . AutoReplyRules::MEDIA_GIF . ',' . AutoReplyRules::MEDIA_VOICE],
            'file' => [
                'required',
                'file',
                'max:' . AutoReplyMedia::MAX_KILOBYTES,
                /*
                 * By the extension: browsers disagree on the type of .oga
                 * and .opus.
                 */
                function (string $attribute, mixed $file, \Closure $fail) use ($type): void {
                    $extension = mb_strtolower($file->getClientOriginalExtension());

                    if (! in_array($extension, AutoReplyMedia::extensions($type), true)) {
                        $fail(__('telegram.auto_replies.validation.media_' . $type));
                    }
                },
            ],
        ]);

        return response()->json([
            'data' => $this->media->store($request->file('file'), $type),
        ], 201);
    }

    /**
     * For the panel's preview.
     */
    public function media(string $file): BinaryFileResponse
    {
        $path = $this->media->path($file);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => match (pathinfo($path, PATHINFO_EXTENSION)) {
                'gif' => 'image/gif',
                'mp4' => 'video/mp4',
                default => 'audio/ogg',
            },
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
