<?php

namespace App\Http\Controllers;

use App\Services\Property\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the global active-property context. Authorization (the property must
 * be one the user may access) is enforced server-side inside
 * PropertyContext::setActiveProperty(), so tampering with the posted id cannot
 * surface another property's data.
 */
class PropertyContextController extends Controller
{
    public function switch(Request $request, PropertyContext $context): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer'],
        ]);

        $property = $context->setActiveProperty((int) $validated['property_id']);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $property->id,
                'name' => $property->name,
            ]);
        }

        // Return to the current page so the user keeps their place after switching.
        return redirect()->back()->with('success', __('messages.property_switched', ['name' => $property->name]));
    }
}
