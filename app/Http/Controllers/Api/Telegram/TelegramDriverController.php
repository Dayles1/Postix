<?php


namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListTelegramDrivers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\TelegramDriverIndexRequest;
use App\Http\Resources\Telegram\TelegramDriverResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TelegramDriverController extends Controller
{
    public function index(
        TelegramDriverIndexRequest $request,
        ListTelegramDrivers $query,
    ): AnonymousResourceCollection {
        return TelegramDriverResource::collection(
            $query->execute(
                TelegramListFilters::fromArray($request->validated()),
            ),
        );
    }
}
