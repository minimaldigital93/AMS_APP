<?php

use App\Services\Period\WorkingMonthContext;

/**
 * The working month: stepping back to a previous month on one business screen
 * keeps every other one on that month, so a month's collection work isn't
 * re-navigated page by page. See App\Services\Period\WorkingMonthContext.
 *
 * The month is still only a DEFAULT — an explicit ?month= always wins, and the
 * fiscal period still clamps what can be shown.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => now()->subYear()->startOfYear()->toDateString(),
        'closing_date' => now()->endOfYear()->toDateString(),
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    $this->rental = makeRental($this->tenant, $this->apartment, ['rent_amount' => 500]);

    $this->lastMonth = now()->subMonthNoOverflow()->startOfMonth();
});

it('carries a month navigated on record income over to record expense', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', [
            'month' => $this->lastMonth->month, 'year' => $this->lastMonth->year,
        ]))
        ->assertOk();

    // The sidebar link — no month of its own.
    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_expense'))
        ->assertOk();

    expect($response->viewData('filterMonth'))->toBe($this->lastMonth->month)
        ->and($response->viewData('filterYear'))->toBe($this->lastMonth->year);
});

it('carries the month to the revenue & expense dashboard and the calendar', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_expense', [
            'month' => $this->lastMonth->month, 'year' => $this->lastMonth->year,
        ]))->assertOk();

    $index = $this->actingAs($this->admin)->get(route('admin.revenue_expense.index'))->assertOk();
    expect($index->viewData('filterMonth'))->toBe($this->lastMonth->month)
        ->and($index->viewData('filterYear'))->toBe($this->lastMonth->year);

    $calendar = $this->actingAs($this->admin)->get(route('admin.revenue_expense.monthly_calendar'))->assertOk();
    expect((int) $calendar->viewData('month'))->toBe($this->lastMonth->month)
        ->and((int) $calendar->viewData('year'))->toBe($this->lastMonth->year);

    $dashboard = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    expect($dashboard->viewData('displayMonth')->month)->toBe($this->lastMonth->month)
        ->and($dashboard->viewData('displayMonth')->year)->toBe($this->lastMonth->year);
});

it('opens on the current month for a session that has navigated nowhere', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'))
        ->assertOk();

    expect($response->viewData('currentMonth'))->toBe(now()->month)
        ->and($response->viewData('currentYear'))->toBe(now()->year)
        ->and($response->viewData('isCurrentMonth'))->toBeTrue();
});

it('lets an explicit month override the remembered one, and the "current month" link reset it', function () {
    $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => $this->lastMonth->month, 'year' => $this->lastMonth->year,
    ]))->assertOk();

    // The header's "go to current month" button posts today's month explicitly.
    $back = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => now()->month, 'year' => now()->year,
    ]))->assertOk();
    expect($back->viewData('isCurrentMonth'))->toBeTrue();

    // …and that reset sticks for the next page too.
    $expense = $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_expense'))->assertOk();
    expect($expense->viewData('filterMonth'))->toBe(now()->month);
});

it('keeps the income statement whole-period view reachable via month=all', function () {
    $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => $this->lastMonth->month, 'year' => $this->lastMonth->year,
    ]))->assertOk();

    // Plain link → follows the working month.
    $monthly = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.income_statement'))->assertOk();
    expect($monthly->viewData('filterMonth'))->toBe($this->lastMonth->month);

    // "View all" → the whole fiscal period, and it does not erase the selection.
    $all = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.income_statement', ['month' => 'all']))->assertOk();
    expect($all->viewData('filterMonth'))->toBeNull();

    expect(app(WorkingMonthContext::class)->selected()->month)->toBe($this->lastMonth->month);
});

it('ignores a bogus month rather than moving the user', function () {
    $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_income', [
        'month' => $this->lastMonth->month, 'year' => $this->lastMonth->year,
    ]))->assertOk();

    $this->actingAs($this->admin)->get(route('admin.revenue_expense.record_expense', [
        'month' => 77, 'year' => 1066,
    ]))->assertOk();

    expect(app(WorkingMonthContext::class)->selected()->month)->toBe($this->lastMonth->month);
});
