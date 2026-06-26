<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Services\NotificationService;
use App\Services\Payment\PaymentManager;
use App\Services\Property\PropertyContext;
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

        // The active-property context is resolved at most once per request.
        $this->app->singleton(PropertyContext::class, fn () => new PropertyContext);
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
            $properties = collect();
            $activeProperty = null;
            $propertySelectorEnabled = false;

            if (Auth::check()) {
                try {
                    $items = app(NotificationService::class)->for(Auth::user());
                } catch (\Throwable $e) {
                    $items = collect();
                }

                try {
                    $context = app(PropertyContext::class);
                    $properties = $context->accessibleProperties();
                    $activeProperty = $context->activeProperty();
                    $propertySelectorEnabled = $context->selectorEnabled();
                } catch (\Throwable $e) {
                    // Never let the selector break a page render.
                    $properties = collect();
                }
            }

            $view->with('topbarNotifications', $items);
            $view->with('topbarProperties', $properties);
            $view->with('topbarActiveProperty', $activeProperty);
            $view->with('topbarPropertySelectorEnabled', $propertySelectorEnabled);
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
