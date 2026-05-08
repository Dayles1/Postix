<?php

use App\Http\Middleware\CheckPermission;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleCheck::class,
            'setLocale' => \App\Http\Middleware\SetLocale::class,
            'check.ban' => \App\Http\Middleware\CheckUserBan::class,
            'permission'  => CheckPermission::class,
        ]);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetLocale::class,
            // \App\Http\Middleware\CheckUserBan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (AuthenticationException $e) {
            return redirect()->route('login');
        });
        /**
         * 404 - Not Found
         */
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {

            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not Found'], 404);
            }

            return response()->view('errors.404', [], 404);
        });

        /**
         * 401 / 403 - Unauthorized / Forbidden
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {

            $status = $e->getStatusCode();
            $user = $request->user();
            $department = $user?->department; // xavfsiz

            if (in_array($status, [401, 403])) {

                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => $status === 401 ? 'Unauthorized' : 'Forbidden'
                    ], $status);
                }

                return response()->view(
                    "errors.$status",
                    compact('department'),
                    $status
                );
            }
        });


        /**
         * 500 - Server Error (faqat haqiqiy crashlar)
         */
        // $exceptions->render(function (\Throwable $e, Request $request) {

        //     if ($request->is('api/*')) {
        //         return response()->json(['message' => 'Server Error'], 500);
        //     }

        //     return response()->view('errors.500', [], 500);
        // });
    })
    ->create();
