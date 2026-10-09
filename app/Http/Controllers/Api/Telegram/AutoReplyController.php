<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\AutoReplyMedia;
use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\AutoReplyTelegramGifs;
use App\Application\Telegram\Services\PersonalAnswers;
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
        private readonly AutoReplyTelegramGifs $telegramGifs,
        private readonly PersonalAnswers $personal,
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

        $this->media->prune([...$rules->mediaFiles(), ...$this->personal->mediaFiles()]);

        return $this->respond(__('telegram.auto_replies.messages.saved'));
    }

    /**
     * The file goes; the config's defaults are back.
     */
    public function destroy(): JsonResponse
    {
        $this->store->reset();

        $this->media->prune([...$this->store->current()->mediaFiles(), ...$this->personal->mediaFiles()]);

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

                        return;
                    }

                    if ($extension === 'mp4' && ! AutoReplyMedia::silentVideo((string) $file->getRealPath())) {
                        $fail(__('telegram.auto_replies.validation.media_gif_sound'));
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

    /**
     * A GIF search in Telegram - the saved GIFs when nothing is typed - left
     * for the listener; telegramGifs() tells when it is done.
     */
    public function searchTelegramGifs(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['nullable', 'string', 'max:60'],
            'offset' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => ['id' => $this->telegramGifs->ask(
                (string) $request->input('query', ''),
                (string) $request->input('offset', ''),
            )],
        ], 202);
    }

    public function telegramGifs(string $id): JsonResponse
    {
        $answer = $this->telegramGifs->answer($id);

        abort_if($answer === null, 404);

        return response()->json(['data' => $answer]);
    }

    /**
     * A GIF found, as the rules keep it: put into them with the next save,
     * like an upload.
     */
    public function pickTelegramGif(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'string', 'regex:/^-?\d{1,20}$/'],
        ]);

        $gif = $this->telegramGifs->pick($id, (string) $request->input('document'));

        abort_if($gif === null, 404, __('telegram.auto_replies.media.telegram_gone'));

        return response()->json([
            'data' => [
                ...$this->media->copy($gif['preview'], $gif['name']),
                'telegram' => $gif['telegram'],
            ],
        ], 201);
    }

    /**
     * A GIF found, for the search's preview.
     */
    public function telegramGifPreview(string $file): BinaryFileResponse
    {
        $path = $this->telegramGifs->previewPath($file);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => pathinfo($path, PATHINFO_EXTENSION) === 'gif' ? 'image/gif' : 'video/mp4',
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
