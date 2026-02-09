<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Get tenant profile for the authenticated user.
     */
    public function show(): TenantResource|JsonResponse
    {
        $tenant = Tenants::where('user_id', Auth::id())
            ->with(['apartment.floor', 'manager'])
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        return new TenantResource($tenant);
    }

    /**
     * Update tenant profile (limited fields).
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = Tenants::where('user_id', Auth::id())->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $validated = $request->validate([
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => new TenantResource($tenant),
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::find(Auth::id());
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Get tenant dashboard summary.
     */
    public function dashboard(): JsonResponse
    {
        $tenant = Tenants::where('user_id', Auth::id())->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $rentalIds = Rentals::where('tenant_id', $tenant->id)->pluck('id')->toArray();

        // Current rental
        $currentRental = Rentals::where('tenant_id', $tenant->id)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->with('apartment.floor')
            ->first();

        // Payment summary
        $pendingPayments = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'pending')
            ->sum('amount');

        $overduePayments = Payments::whereIn('rental_id', $rentalIds)
            ->where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->sum('amount');

        $nextPayment = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'pending')
            ->orderBy('due_date', 'asc')
            ->first();

        // Utility summary
        $unpaidUtilities = Utilities::where('tenant_id', $tenant->id)
            ->where('paid_status', false)
            ->sum('charge_amount');

        return response()->json([
            'tenant' => new TenantResource($tenant->load('apartment.floor')),
            'current_rental' => $currentRental ? [
                'id' => $currentRental->id,
                'apartment' => $currentRental->apartment->apartment_number ?? null,
                'floor' => $currentRental->apartment->floor->floor_name ?? null,
                'rent_amount' => $currentRental->rent_amount,
                'start_date' => $currentRental->start_date->format('Y-m-d'),
                'end_date' => $currentRental->end_date?->format('Y-m-d'),
            ] : null,
            'payments' => [
                'pending_amount' => $pendingPayments,
                'overdue_amount' => $overduePayments,
                'next_due_date' => $nextPayment?->due_date?->format('Y-m-d'),
                'next_amount' => $nextPayment?->amount,
            ],
            'utilities' => [
                'unpaid_amount' => $unpaidUtilities,
            ],
        ]);
    }
}
