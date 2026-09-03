<?php

namespace App\Http\Middleware;

use App\Services\Period\WorkingMonthContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the month the user navigates to, so every other business screen
 * opens on it (see WorkingMonthContext).
 *
 * The capture lives here rather than in each controller because the month
 * arrives the same way everywhere — a ?month=&year= link — and a controller
 * that only reads the context inside a fallback branch would never record the
 * month it was itself given.
 *
 * GET only: a POST carries billing_month/billing_year (the month a payment
 * settles), which is a different question and must not move the user's view.
 * A non-numeric month (the income statement's ?month=all whole-period link)
 * reads as 0 and is ignored, so "view all" doesn't erase the selection.
 */
class SetWorkingMonth
{
    public function __construct(private WorkingMonthContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && Auth::check()) {
            $this->context->remember(
                $request->integer('month') ?: null,
                $request->integer('year') ?: null,
            );
        }

        return $next($request);
    }
}
