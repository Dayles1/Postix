<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\FileRouteRequest;
use App\Models\Telegram\TelegramFileForward;
use App\Models\Telegram\TelegramFileRoute;
use Illuminate\Http\JsonResponse;

/**
 * The "Fayllar" page: which kind of the notifications bot's files goes to
 * which groups, and what was sent lately. The listener reads the routes
 * on every file, so a change applies to the next one.
 */
final class FileRouteController extends Controller
{
    private const RECENT = 50;

    public function index(): JsonResponse
    {
        $routes = TelegramFileRoute::query()
            ->withCount([
                'forwards as sent_count' => fn ($q) => $q->where('status', TelegramFileForward::STATUS_SENT),
            ])
            ->withMax('forwards as last_sent_at', 'sent_at')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $recent = TelegramFileForward::query()
            ->with('route:id,name')
            ->latest('id')
            ->limit(self::RECENT)
            ->get();

        return response()->json([
            'routes' => $routes->map(fn (TelegramFileRoute $route): array => $this->route($route))->all(),
            'recent' => $recent->map(fn (TelegramFileForward $forward): array => [
                'id' => $forward->id,
                'route' => $forward->route?->name,
                'file_name' => $forward->file_name,
                'caption' => $forward->caption,
                'target_chat_id' => $forward->target_chat_id,
                'status' => $forward->status,
                'attempts' => $forward->attempts,
                'error' => $forward->error,
                'sent_at' => $forward->sent_at,
                'created_at' => $forward->created_at,
            ])->all(),
            'source_chat_ids' => array_values((array) config('file_routes.source_chat_ids', [])),
        ]);
    }

    public function store(FileRouteRequest $request): JsonResponse
    {
        $attributes = $request->attributesToSave();

        $attributes['sort'] ??= (int) TelegramFileRoute::query()->max('sort') + 1;

        $route = TelegramFileRoute::query()->create($attributes);

        return response()->json(['data' => $this->route($route)], 201);
    }

    public function update(FileRouteRequest $request, TelegramFileRoute $fileRoute): JsonResponse
    {
        $fileRoute->update($request->attributesToSave());

        return response()->json(['data' => $this->route($fileRoute)]);
    }

    /**
     * The forwards already made stay in the journal, without a kind.
     */
    public function destroy(TelegramFileRoute $fileRoute): JsonResponse
    {
        $fileRoute->delete();

        return response()->json(['message' => __('telegram.file_routes.messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function route(TelegramFileRoute $route): array
    {
        return [
            'id' => $route->id,
            'name' => $route->name,
            'keywords' => $route->keywords,
            'target_chat_ids' => $route->targets(),
            'is_active' => (bool) $route->is_active,
            'sort' => $route->sort,
            'sent_count' => (int) ($route->sent_count ?? 0),
            'last_sent_at' => $route->last_sent_at ?? null,
        ];
    }
}
