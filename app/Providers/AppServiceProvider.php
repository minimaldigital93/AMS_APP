<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\Payment\PaymentManager;
use App\Services\Subscription\SubscriptionService;
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
        $this->app->singleton(PaymentManager::class, fn () => new PaymentManager);
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

        // The subscription-expired blocking modal: mirrors EnsureSubscriptionActive
        // (an admin — never a superadmin — with no active subscription is locked
        // out). Resolves which plan to re-pay so the modal can mint a fresh QR.
        View::composer('partials.subscription-block', function ($view) {
            $blocked = false;
            $plan = null;

            try {
                $user = Auth::user();
                if ($user && $user->hasRole('admin') && ! $user->hasRole('superadmin')) {
                    $accountId = $user->account_id ?? $user->id;
                    if (app(SubscriptionService::class)->activeSubscription($accountId) === null) {
                        $blocked = true;
                        $plan = Subscription::where('account_id', $accountId)
                            ->with('plan')->latest('id')->first()?->plan;
                    }
                }
            } catch (\Throwable $e) {
                // Never let the gate-modal computation break a page render.
                $blocked = false;
            }

            $view->with('subscriptionBlocked', $blocked);
            $view->with('subscriptionRenewPlan', $plan);
        });
    }
}
