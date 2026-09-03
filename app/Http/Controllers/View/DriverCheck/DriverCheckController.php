<?php

declare(strict_types=1);

namespace App\Http\Controllers\View\DriverCheck;

use App\Http\Controllers\Controller;
use App\Models\Telegram\OperationUser;
use Illuminate\Http\Request;

final class DriverCheckController extends Controller
{
    public function operationUsers(Request $request)
    {
        $this->authorizeDriverCheck($request);

        return view(
            'pages.driver-check.operation-users'
        );
    }

    public function drivers(Request $request)
    {
        $this->authorizeDriverCheck($request);

        return view(
            'pages.driver-check.drivers'
        );
    }

    public function resolvedPhones(Request $request)
    {
        $this->authorizeDriverCheck($request);

        return view(
            'pages.driver-check.resolved-phones'
        );
    }

    private function authorizeDriverCheck(
        Request $request
    ): void {
        $user = $request->user();

        if (
            !$user
            || ($user->role->name ?? null) !== 'driverCheck'
        ) {
            abort(403);
        }
    }
    public function operationUser(
        Request $request,
        OperationUser $operationUser,
    ) {
        $this->authorizeDriverCheck($request);

        return view(
            'pages.driver-check.operation-user',
            compact('operationUser'),
        );
    }
}