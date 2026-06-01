<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Superadmin CRUD for subscription plans (price + floor/apartment caps).
 */
class PlansController extends Controller
{
    public function index(): View
    {
        return view('superadmin.plans.index', [
            'plans' => Plan::orderBy('price_usd')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'max_floors' => ['nullable', 'integer', 'min:0'],
            'max_apartments' => ['nullable', 'integer', 'min:0'],
            'billing_period_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'name' => $validated['name'],
            'price_usd' => $validated['price_usd'],
            // Blank = unlimited.
            'max_floors' => $validated['max_floors'] ?? null,
            'max_apartments' => $validated['max_apartments'] ?? null,
            'billing_period_days' => $validated['billing_period_days'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('messages.flash_plan_updated', ['plan' => $plan->name]));
    }
}
