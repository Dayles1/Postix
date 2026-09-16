<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Exports\DriverCheckDetailExport;
use App\Exports\DriverCheckOperatorsExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DriverCheckExportController extends Controller
{
    /**
     * GET /api/telegram/driver-check/export/operators
     *
     * Default period: last 7 calendar days including today.
     *
     * Optional filters:
     * - operation_user_id
     * - from=YYYY-MM-DD
     * - to=YYYY-MM-DD
     * - status=confirmed|not_confirmed|pending|processing
     * - search
     * - detail=1  -> exports full operator/driver/check details instead
     */
    public function operators(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        $filters = $this->resolveFilters($request, $from, $to);

        if ($request->boolean('detail')) {
            $fileName = sprintf(
                'driver-check-details_%s_%s.xlsx',
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            );

            return Excel::download(
                new DriverCheckDetailExport($filters),
                $fileName,
            );
        }

        $fileName = sprintf(
            'driver-check-operators_%s_%s.xlsx',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return Excel::download(
            new DriverCheckOperatorsExport($filters),
            $fileName,
        );
    }

    /**
     * GET /api/telegram/driver-check/export/details
     *
     * Explicit detail endpoint. Same filters as operators().
     */
    public function details(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        $filters = $this->resolveFilters($request, $from, $to);

        $fileName = sprintf(
            'driver-check-details_%s_%s.xlsx',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return Excel::download(
            new DriverCheckDetailExport($filters),
            $fileName,
        );
    }

    /**
     * Default = the last 7 calendar days including today.
     * Example on 2026-08-28: 2026-08-22 00:00:00 .. 2026-08-28 23:59:59.
     */
    private function resolvePeriod(Request $request): array
    {
        $now = now();

        $from = $request->filled('from')
            ? Carbon::parse((string) $request->input('from'))->startOfDay()
            : $now->copy()->subDays(6)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse((string) $request->input('to'))->endOfDay()
            : $now->copy()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * Filters shared by both the operators and the details export.
     * These mirror the filter set the driver-check list pages already
     * expose in the UI, so "Export to Excel" respects whatever the
     * operator currently has filtered/searched on screen.
     */
    private function resolveFilters(Request $request, Carbon $from, Carbon $to): array
    {
        return [
            'operation_user_id' => $request->integer('operation_user_id') ?: null,
            'driver_id' => $request->integer('driver_id') ?: null,
            'status' => $request->filled('status')
                ? (string) $request->string('status')
                : null,
            'search' => $request->filled('search')
                ? trim((string) $request->string('search'))
                : null,
            'telegram_username' => $request->filled('telegram_username')
                ? trim((string) $request->string('telegram_username'))
                : null,
            'min_match_score' => $request->filled('min_match_score')
                ? (float) $request->input('min_match_score')
                : null,
            'max_match_score' => $request->filled('max_match_score')
                ? (float) $request->input('max_match_score')
                : null,
            'from' => $from->copy(),
            'to' => $to->copy(),
        ];
    }
}
