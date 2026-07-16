<?php

use App\Models\MonthlyPeriod;

/**
 * The standalone printable documents (monthly transaction summary) must keep
 * rendering with the shared print letterhead/footer components
 * (resources/views/components/print/).
 */
it('renders the monthly period printable document with the print letterhead', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);
    $fp = makeFiscalPeriod($admin);
    $month = MonthlyPeriod::create([
        'fiscal_period_id' => $fp->id, 'user_id' => $admin->id,
        'name' => now()->format('F Y'),
        'month_number' => now()->month, 'year' => now()->year,
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'status' => 'open',
    ]);

    $this->get(route('admin.fiscalperiod.monthly-period.print', [$fp->id, $month->id]))
        ->assertOk()
        ->assertSee(__('messages.monthly_transaction_summary'))
        ->assertSee(__('messages.apartment_management_system'))
        ->assertSee(__('messages.generated_by'));
});
