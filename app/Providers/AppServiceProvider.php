<?php

namespace App\Providers;

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
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationsEnded::class, function () {
            \Illuminate\Support\Facades\Artisan::call('docs:update');
        });

        // Gate definition for opcodesio/log-viewer
        \Illuminate\Support\Facades\Gate::define('viewLogViewer', function ($user = null) {
            if (!$user) {
                return app()->environment('local');
            }
            return $user->hasRole('cashier') || $user->hasPermissionTo('pos.access') || app()->environment('local');
        });
    }
}
