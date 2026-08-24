<?php

namespace App\Http\Controllers\Api\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\StartTelegramWatchdogJob;
use Illuminate\Http\JsonResponse;

class TelegramDriverCheckController extends Controller
{
    public function start(): JsonResponse
    {
        StartTelegramWatchdogJob::dispatch()->onQueue('telegram');

        return response()->json([
            'success' => true,
            'message' => 'Telegram watchdog start job dispatched.',
        ]);
    }
}