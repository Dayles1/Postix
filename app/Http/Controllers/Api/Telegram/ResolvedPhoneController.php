<?php

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListResolvedPhones;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\ResolvedPhoneIndexRequest;
use App\Http\Resources\Telegram\ResolvedPhoneResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ResolvedPhoneController extends Controller
{
    public function index(
        ResolvedPhoneIndexRequest $request,
        ListResolvedPhones $query,
    ): AnonymousResourceCollection {
        return ResolvedPhoneResource::collection(
            $query->execute(
                TelegramListFilters::fromArray($request->validated()),
            ),
        );
    }
}
