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
        // A POST body over php.ini's post_max_size throws before the session
        // middleware runs, so a flash-redirect is usually impossible — render
        // the friendly 413 page instead of the framework error page. When a
        // session IS present (exception raised later in the stack), bounce the
        // user back to the form with a normal error flash.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            $message = __('The uploaded file exceeds the server upload limit. Please choose a smaller file and try again.');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            if ($request->hasSession()) {
                return redirect(url()->previous())->with('error', $message);
            }

            return response()->view('errors.413', [], 413);
        });

        // Attach identifying context to every reported exception so production
        // errors can be traced back to a user / account / request. This runs on
        // every log write, so it is fully defensive and must never throw — a
        // failure here must not mask the original error.
        $exceptions->context(function (): array {
            try {
                return array_filter([
                    'user_id' => auth()->id(),
                    'account_id' => function_exists('current_account_id') ? current_account_id() : null,
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip' => request()->ip(),
                ], fn ($value) => $value !== null);
            } catch (\Throwable $e) {
                return [];
            }
        });
    })->create();
