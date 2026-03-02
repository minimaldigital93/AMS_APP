<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\FiscalPeriods;

class EnsureFiscalPeriodExists
{
    /**
     * Ensure an active fiscal period exists before allowing transaction recording.
     * If no open fiscal period exists, redirect to the fiscal period creation page.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $hasActivePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->exists();

        if (!$hasActivePeriod) {
            return redirect()->route('admin.fiscalperiod.create')
                ->with('warning', 'You must create a fiscal period before recording transactions. A fiscal period is required to track revenue from rent, record expenses, and manage your financial data.');
        }

        return $next($request);
    }
}
