<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin CRUD for properties (buildings) — the top of the property tree. A
 * property may be assigned to one supervisor, who then only sees that property's
 * floors/rooms/tenants (see Supervisor controllers).
 */
class PropertyController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $query = Property::with('supervisor')
            ->withCount(['floors', 'apartments']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $properties = $query->orderBy('name')->paginate(12)->withQueryString();
        $usage = $this->subscriptions->usage(current_account_id());

        return view('admin.properties.index', compact('properties', 'usage'));
    }

    public function create(): View
    {
        return view('admin.properties.create', [
            'supervisors' => User::where('account_id', current_account_id())->role('supervisor')->orderBy('name')->get(),
        ]);
    }

    public function edit(Property $property): View
    {
        return view('admin.properties.edit', [
            'property' => $property,
            'supervisors' => User::where('account_id', current_account_id())->role('supervisor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProperty($request);

        $accountId = current_account_id();
        if (! $this->subscriptions->canAddProperties($accountId)) {
            $plan = $this->subscriptions->activePlan($accountId);

            return back()->withInput()->with('error', __('messages.flash_plan_limit_properties', ['plan' => $plan?->name, 'max' => $plan?->max_properties]));
        }

        Property::create($validated);

        return redirect()->route('admin.properties.index')->with('success', __('messages.flash_property_created'));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $property->update($this->validateProperty($request));

        return redirect()->route('admin.properties.index')->with('success', __('messages.flash_property_updated'));
    }

    public function destroy(Property $property): RedirectResponse
    {
        // Don't orphan floors/rooms — require the property be empty first.
        if ($property->floors()->exists()) {
            return back()->with('error', __('messages.flash_property_has_floors'));
        }

        $property->delete();

        return redirect()->route('admin.properties.index')->with('success', __('messages.flash_property_deleted'));
    }

    /**
     * Shared validation. supervisor_id must be a supervisor on THIS account.
     */
    private function validateProperty(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:65535',
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('account_id', current_account_id()),
            ],
        ]);
    }
}
