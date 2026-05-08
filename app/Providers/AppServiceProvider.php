<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            if (! $user) {
                // anonim foydalanuvchi uchun oddiy include
                $view->with('cachedHeader', view('partials.header')->render());
                return;
            }

            $cacheKey = 'header_html_user_' . $user->id . '_' . app()->getLocale();

            $html = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
                // ensure relations already loaded for consistent rendering
                $user->loadMissing(['avatar', 'role']);
                return view('layouts.app-header', ['user' => $user])->render();
            });

            $view->with('cachedHeader', $html);
        });
    }
}
