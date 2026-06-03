<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        // Plans power the "Subscribe" pricing modal on the login screen.
        $plans = Plan::where('is_active', true)->orderBy('price_usd')->get();

        return view('auth.login', compact('plans'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Clear any previously stored intended URL to prevent cross-role redirects
        $request->session()->forget('url.intended');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->hasRole('superadmin')) {
            return redirect()->route('superadmin.dashboard');
        } elseif ($user->hasRole('admin')) {
            // First login after registering/subscribing: a freshly promoted admin
            // has no fiscal period yet, so send them straight to create one. Once
            // a period exists, subsequent logins land on the dashboard.
            $hasFiscalPeriod = \App\Models\FiscalPeriods::where('user_id', $user->id)->exists();

            return $hasFiscalPeriod
                ? redirect()->route('admin.dashboard')
                : redirect()->route('admin.fiscalperiod.create');
        } elseif ($user->hasRole('supervisor')) {
            return redirect()->route('supervisor.dashboard');
        } elseif ($user->hasRole('tenant')) {
            return redirect()->route('tenant.dashboard');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
