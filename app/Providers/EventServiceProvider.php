<?php

namespace App\Providers;

use App\Models\Department;
use App\Observers\DepartmentObserver;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
{
    Department::observe(DepartmentObserver::class);
}
}
