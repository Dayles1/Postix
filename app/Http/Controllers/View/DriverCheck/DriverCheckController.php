<?php

declare(strict_types=1);

namespace App\Http\Controllers\View\DriverCheck;

use App\Http\Controllers\Controller;
use App\Models\Telegram\OperationUser;

/**
 * Access to this whole controller is enforced by the `role:driverCheck,
 * superadmin` middleware on the route group (see routes/web.php), so no
 * per-method authorization check is needed here.
 */
final class DriverCheckController extends Controller
{
    public function operationUsers()
    {
        return view(
            'pages.driver-check.operation-users'
        );
    }

    public function operationUser(OperationUser $operationUser)
    {
        return view(
            'pages.driver-check.operation-user',
            compact('operationUser'),
        );
    }

    public function operators()
    {
        return view(
            'pages.driver-check.operators'
        );
    }

    public function drivers()
    {
        return view(
            'pages.driver-check.drivers'
        );
    }

    public function resolvedPhones()
    {
        return view(
            'pages.driver-check.resolved-phones'
        );
    }
}
