<?php

namespace App\Http\Middleware;

use App\Models\FiscalPeriods;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFiscalPeriodExists
{
    /**
     * Gate routes that record financial transactions on the presence of an
     * open fiscal period.
     *
     * Admin:      must have their own open period.
     * Supervisor: writes land in the admin's books (see CLAUDE.md sec. 2), so
     *             we require that at least one admin has an open period.
     *             Supervisors can't open admin periods themselves, so on
     *             failure we surface a warning rather than bouncing them
     *             into the admin-only creation route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user->hasRole('admin')) {
            $hasPeriod = FiscalPeriods::where('user_id', $user->id)
                ->where('status', 'open')
                ->exists();

            if (! $hasPeriod) {
                return redirect()->route('admin.fiscalperiod.create')
                    ->with('warning', 'You must create a fiscal period before recording transactions. A fiscal period is required to track revenue from rent, record expenses, and manage your financial data.');
            }

            return $next($request);
        }

        if ($user->hasRole('supervisor')) {
            $hasAdminPeriod = FiscalPeriods::where('status', 'open')
                ->whereHas('user', fn ($q) => $q->role('admin'))
                ->exists();

            if (! $hasAdminPeriod) {
                return redirect()->route('supervisor.dashboard')
                    ->with('warning', 'No active fiscal period. Ask an admin to open one before recording transactions.');
            }

            return $next($request);
        }

        return $next($request);
    }
}
