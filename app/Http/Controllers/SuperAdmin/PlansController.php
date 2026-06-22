<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_yearly_usd' => ['nullable', 'numeric', 'min:0'],
            'max_properties' => ['nullable', 'integer', 'min:0'],
            'max_floors' => ['nullable', 'integer', 'min:0'],
            'max_rooms' => ['nullable', 'integer', 'min:0'],
            'max_staff' => ['nullable', 'integer', 'min:0'],
            'billing_period_days' => ['required', 'integer', 'min:1'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan = Plan::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'price_usd' => $validated['price_usd'],
            'price_yearly_usd' => $validated['price_yearly_usd'] ?? null,
            // Blank = unlimited.
            'max_properties' => $validated['max_properties'] ?? null,
            'max_floors' => $validated['max_floors'] ?? null,
            'max_rooms' => $validated['max_rooms'] ?? null,
            'max_staff' => $validated['max_staff'] ?? null,
            'billing_period_days' => $validated['billing_period_days'],
            'trial_days' => (int) ($validated['trial_days'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('superadmin.plans.index')
            ->with('success', __('messages.flash_plan_created', ['plan' => $plan->name]));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_yearly_usd' => ['nullable', 'numeric', 'min:0'],
            'max_properties' => ['nullable', 'integer', 'min:0'],
            'max_floors' => ['nullable', 'integer', 'min:0'],
            'max_rooms' => ['nullable', 'integer', 'min:0'],
            'max_staff' => ['nullable', 'integer', 'min:0'],
            'billing_period_days' => ['required', 'integer', 'min:1'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'name' => $validated['name'],
            'price_usd' => $validated['price_usd'],
            'price_yearly_usd' => $validated['price_yearly_usd'] ?? null,
            // Blank = unlimited.
            'max_properties' => $validated['max_properties'] ?? null,
            'max_floors' => $validated['max_floors'] ?? null,
            'max_rooms' => $validated['max_rooms'] ?? null,
            'max_staff' => $validated['max_staff'] ?? null,
            'billing_period_days' => $validated['billing_period_days'],
            'trial_days' => (int) ($validated['trial_days'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('messages.flash_plan_updated', ['plan' => $plan->name]));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Don't orphan accounts: block deletion while the plan is in use.
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', __('messages.flash_plan_in_use', ['plan' => $plan->name]));
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->route('superadmin.plans.index')
            ->with('success', __('messages.flash_plan_deleted', ['plan' => $name]));
    }
}
