<?php


namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListOperationUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\OperationUserIndexRequest;
use App\Http\Resources\Telegram\OperationUserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OperationUserController extends Controller
{
    public function index(
        OperationUserIndexRequest $request,
        ListOperationUsers $query,
    ): AnonymousResourceCollection {
        return OperationUserResource::collection(
            $query->execute(
                TelegramListFilters::fromArray($request->validated()),
            ),
        );
    }
}
