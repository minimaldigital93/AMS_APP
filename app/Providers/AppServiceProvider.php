<?php

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, fn () => new NotificationService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production or when FORCE_HTTPS is enabled
        if ($this->app->environment('production') || env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        View::composer('layouts.topbar', function ($view) {
            $items = collect();
            if (Auth::check()) {
                try {
                    $items = app(NotificationService::class)->for(Auth::user());
                } catch (\Throwable $e) {
                    $items = collect();
                }
            }
            $view->with('topbarNotifications', $items);
        });
    }
}
