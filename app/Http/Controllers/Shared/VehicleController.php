<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Models\Floors;
use App\Models\TenantVehicle;
use App\Models\Utilities;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Property-wide vehicle management: every registered vehicle laid out by floor
 * and room, shared by both panels — one implementation, two thin subclasses
 * that only pin the panel slug.
 *
 * This page **reads**; every write goes to Shared\TenantVehicleController, the
 * one write path, which the tenant-detail card already uses. Each room's form
 * is rendered bound to that room's sitting tenant and posts the room id
 * alongside, so the controller can confirm the tenant is still there.
 *
 * A vehicle belongs to a tenant and the room comes through them
 * (`TenantVehicle::room()`), so the verification this page exists to give is
 * simply: does every vehicle still resolve to a live tenant sitting in a room?
 * The ones that don't are collected into $unverified rather than hidden — a
 * tenant who moved out is soft-deleted, which leaves their vehicles behind with
 * no room to park in and no other screen that would ever show them.
 */
abstract class VehicleController extends Controller
{
    use ScopesToSupervisorProperties;

    /** Panel slug ('admin' or 'supervisor') — drives every route() in the view. */
    abstract protected function panel(): string;

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $type = $request->input('type');

        if (! in_array($type, TenantVehicle::TYPES, true)) {
            $type = null;
        }

        // Floors carry the layout: property scope (top-bar selector) and
        // supervisor property scope both land here, and the rooms/tenants/
        // vehicles hang off them, so nothing below re-queries per room.
        $floorsQuery = $this->seesWholeAccount()
            ? Floors::query()
            : Floors::query()->whereIn('property_id', $this->supervisorPropertyIds());

        // Same ordering key as the Active Tenants roster (Admin\TenantController):
        // property name, then floor **id**, then room number naturally. Floor id
        // is creation order — how the building was entered, G → 1 → 2 — where
        // sorting on floor_name reads "1st, 2nd, Ground" and puts the ground
        // floor last. The two pages list the same building; they list it alike.
        $floors = $floorsQuery
            ->forActiveProperty()
            ->with(['property', 'apartments.tenants.vehicles'])
            ->get()
            ->sortBy(
                fn ($floor) => sprintf('%s|%020d', $floor->property?->name ?? '~', $floor->id),
                SORT_NATURAL | SORT_FLAG_CASE
            )
            ->values();

        $totalVehicles = 0;
        $totalBilled = 0.0;
        $billableCount = 0;
        $typeCounts = array_fill_keys(TenantVehicle::TYPES, 0);
        $groups = [];

        foreach ($floors as $floor) {
            $rooms = [];

            $apartments = $floor->apartments->sortBy('apartment_number', SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($apartments as $room) {
                // Same "current tenant of a room" rule the floors page uses:
                // the newest live tenant row (a departed one is soft-deleted).
                $tenant = $room->tenants->sortByDesc('id')->first();
                $vehicles = $tenant ? $tenant->vehicles : collect();

                // Totals are accumulated for every room, including ones the
                // search or type chip drops below — the tiles describe the
                // property, not the filtered view.
                $totalVehicles += $vehicles->count();
                $totalBilled += $vehicles->sum(fn ($v) => $v->isBillable() ? (float) $v->monthly_fee : 0.0);
                $billableCount += $vehicles->filter(fn ($v) => $v->isBillable())->count();

                foreach ($vehicles as $vehicle) {
                    if (array_key_exists($vehicle->vehicle_type, $typeCounts)) {
                        $typeCounts[$vehicle->vehicle_type]++;
                    }
                }

                // The tenant and room are already in hand here; handing them to
                // the search keeps it off $vehicle->tenant, which would be a
                // lazy load per vehicle.
                $visible = $this->matching($vehicles, $search, $type, $tenant?->name.' '.$room->apartment_number);

                // A room with no vehicles is still worth a row — it is where
                // the first one gets added — as long as someone is living in it
                // and no filter has narrowed past it. The search reaches the
                // room and tenant here so an occupied but vehicle-less room
                // still answers a search for either.
                $roomMatchesSearch = $search !== '' && str_contains(
                    mb_strtolower($room->apartment_number.' '.$tenant?->name),
                    mb_strtolower($search)
                );
                $keep = $visible->isNotEmpty()
                    || ($tenant !== null && ($search === '' || $roomMatchesSearch) && $type === null);

                if (! $keep) {
                    continue;
                }

                $rooms[] = [
                    'room' => $room,
                    'tenant' => $tenant,
                    'vehicles' => $visible,
                    'fee' => $visible->sum(fn ($v) => $v->isBillable() ? (float) $v->monthly_fee : 0.0),
                ];
            }

            if ($rooms === []) {
                continue;
            }

            $groups[] = [
                'floor' => $floor,
                'rooms' => $rooms,
                'vehicle_count' => array_sum(array_map(fn ($r) => $r['vehicles']->count(), $rooms)),
                'fee' => array_sum(array_column($rooms, 'fee')),
            ];
        }

        $parking = $this->parkingMonth();

        return view('shared.vehicles.index', [
            'panel' => $this->panel(),
            'groups' => $groups,
            'unverified' => $this->unverified($search, $type),
            'totalVehicles' => $totalVehicles,
            'totalBilled' => $totalBilled,
            'billableCount' => $billableCount,
            'typeCounts' => $typeCounts,
            'parkingRevenue' => $parking['revenue'],
            'parkingOutstanding' => $parking['outstanding'],
            'search' => $search,
            'type' => $type,
        ]);
    }

    /**
     * This month's parking money, read off the `parking` Utilities rows — the
     * one lane a priced vehicle is billed through (MonthlyBillingService). The
     * vehicle rows themselves say what the *next* bill run will charge; only
     * the charge rows say what was actually billed and collected.
     *
     * Revenue is keyed on `paid_at` (money received this month, same definition
     * the income statement's parking line uses); outstanding is what this
     * month's bill raised and nobody has settled yet.
     *
     * @return array{revenue: float, outstanding: float}
     */
    private function parkingMonth(): array
    {
        $now = Carbon::now();

        $base = fn () => $this->scopeParkingToVisibleRooms(
            Utilities::query()->forActiveProperty()->where('utility_type', 'parking')
        );

        return [
            'revenue' => round((float) $base()->paid()
                ->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->sum('charge_amount'), 2),
            'outstanding' => round((float) $base()->unpaid()
                ->forMonth($now->month, $now->year)
                ->sum('charge_amount'), 2),
        ];
    }

    /**
     * Supervisors see the charges of their assigned properties only; admins are
     * already isolated by the account scope (seesWholeAccount()).
     */
    private function scopeParkingToVisibleRooms(Builder $query): Builder
    {
        if ($this->seesWholeAccount()) {
            return $query;
        }

        $apartmentIds = $this->supervisorApartmentIds();

        return $query->whereHas('rental', fn (Builder $q) => $q->whereIn('apartment_id', $apartmentIds));
    }

    /**
     * Vehicles that no longer resolve to a live tenant in a room — the whole
     * point of the verification. Left by a tenant moving out (soft-deleted, so
     * the FK cascade never fires) or registered against a tenant with no room.
     *
     * Supervisors don't get this list: with no room, a vehicle has no property,
     * so there is nothing to match against their assignments. It is the account
     * owner's tidy-up either way.
     */
    private function unverified(string $search, ?string $type): Collection
    {
        if (! $this->seesWholeAccount()) {
            return collect();
        }

        return TenantVehicle::with('tenant')
            ->get()
            ->reject(fn (TenantVehicle $vehicle) => $vehicle->isVerified())
            ->pipe(fn (Collection $orphans) => $this->matching($orphans, $search, $type));
    }

    /**
     * Apply the plate/model search and the type chip to a vehicle set.
     *
     * `$context` is the tenant name and room number the caller already resolved
     * — searching on them without re-reading the relation per vehicle.
     */
    private function matching(Collection $vehicles, string $search, ?string $type, string $context = ''): Collection
    {
        if ($type !== null) {
            $vehicles = $vehicles->where('vehicle_type', $type);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $vehicles = $vehicles->filter(function (TenantVehicle $vehicle) use ($needle, $context) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $vehicle->plate_number,
                    $vehicle->vehicle_model,
                    $context !== '' ? $context : $vehicle->tenant?->name,
                ])));

                return str_contains($haystack, $needle);
            });
        }

        return $vehicles->values();
    }
}
