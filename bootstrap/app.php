<?php

use App\Http\Middleware\EnsureFiscalPeriodExists;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetPropertyContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the Cloudflare Tunnel + nginx, requests arrive over a local
        // proxy. Trust it so the app detects the original HTTPS scheme, client
        // IP, host, and the X-Forwarded-Prefix (/ams_app) that nginx injects —
        // so generated URLs, redirects and assets keep the sub-path prefix.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->web(append: [
            SetLocale::class,
            SetPropertyContext::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'fiscal.period' => EnsureFiscalPeriodExists::class,
            'subscription.active' => EnsureSubscriptionActive::class,
        ]);

        // KHQRPay webhook is authenticated by its own signature, not a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'khqr/callback',
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
