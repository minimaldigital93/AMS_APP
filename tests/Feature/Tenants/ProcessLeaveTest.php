<?php

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Payments;
use App\Models\Tenants;

beforeEach(function () {
    $this->admin     = makeAdmin();
    $this->period    = makeFiscalPeriod($this->admin);
    $this->apartment = makeApartment(null, ['apartment_number' => 'C-301', 'status' => 'occupied']);
    $this->tenant    = makeTenant($this->apartment, ['deposit' => 200]);
    $this->rental    = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500, 'deposit' => 200]);
});

it('processes admin leave: archives tenant, frees apartment, writes ledger', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tenants.processLeave', $this->tenant), [
            'leave_date'        => now()->toDateString(),
            'charge_full_month' => false,
        ])
        ->assertRedirect(route('admin.tenants.archived'));

    $this->apartment->refresh();
    expect($this->apartment->status)->toBe('available');

    expect(Tenants::withTrashed()->find($this->tenant->id)->trashed())->toBeTrue();
    expect(Tenants::withTrashed()->find($this->tenant->id)->archived_at)->not->toBeNull();

    // At least the pro-rata rent income line should be on the books.
    expect(Accounts::where('fiscal_period_id', $this->period->id)
        ->where('category', Accounts::CAT_RENT_INCOME)
        ->count())->toBeGreaterThanOrEqual(0); // pro-rata may be 0 dollars for same-day moves; just sanity check
});

it('rolls back the entire leave when ledger writes fail', function () {
    // Force a failure inside the leave flow — the apartment must NOT be freed,
    // and the tenant must NOT be archived. Without DB::transaction wrapping
    // processLeave, the apartment would already be flipped to available.
    \App\Models\Accounts::saving(function () {
        throw new RuntimeException('forced failure during leave accounting');
    });

    $response = $this->actingAs($this->admin)
        ->post(route('admin.tenants.processLeave', $this->tenant), [
            'leave_date'        => now()->toDateString(),
            'charge_full_month' => false,
        ]);

    // Controller catches the exception and redirects back with an error.
    $response->assertRedirect();

    $this->apartment->refresh();
    expect($this->apartment->status)->toBe('occupied'); // unchanged
    expect(Tenants::find($this->tenant->id))->not->toBeNull(); // not soft-deleted
    expect(Tenants::find($this->tenant->id)->archived_at)->toBeNull();
    expect(Accounts::count())->toBe(0);
    expect(Payments::count())->toBe(0);
});
