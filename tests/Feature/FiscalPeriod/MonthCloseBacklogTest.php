<?php

use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Services\FiscalPeriod\MonthCloseBacklog;
use App\Services\RevenueExpense\IncomeRecordingService;
use Carbon\Carbon;

/**
 * Closing a month is what turns its figures into books, so the app both asks
 * for it and eventually insists:
 *
 *   0 finished months open  →  nothing
 *   1                       →  amber dashboard banner, everything still works
 *   2 or more               →  red banner, and recording income/expenses stops
 *                              until the oldest is closed
 *
 * "Finished" means the month has ended — the month in progress is never due,
 * so exactly one becomes due on the 1st and the account has a full month to
 * clear it before a second joins it.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-09-10');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function makeMonth(int $month, string $status = 'open'): MonthlyPeriod
{
    $start = Carbon::create(2026, $month, 1);

    return MonthlyPeriod::create([
        'fiscal_period_id' => test()->period->id,
        'user_id' => test()->admin->id,
        'name' => $start->format('F Y'),
        'month_number' => $month,
        'year' => 2026,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->endOfMonth()->toDateString(),
        'opening_balance' => 0,
        'closing_balance' => 0,
        'total_income' => 0,
        'total_expenses' => 0,
        'net_income' => 0,
        'status' => $status,
    ]);
}

/** A write into the books — blocked by the backlog, not by its own validation. */
function attemptWrite(): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.revenue_expense.add_charge'), []);
}

it('says nothing while only the month in progress is open', function () {
    makeMonth(7, 'closed');
    makeMonth(8, 'closed');
    makeMonth(9); // September is still running on the 10th

    expect(app(MonthCloseBacklog::class)->build())->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('monthCloseAlert', null);

    attemptWrite()->assertSessionMissing('error');
});

it('asks the admin to close the one month that has ended, without blocking anything', function () {
    makeMonth(7, 'closed');
    makeMonth(8);
    makeMonth(9);

    $backlog = app(MonthCloseBacklog::class)->build();
    expect($backlog['count'])->toBe(1)
        ->and($backlog['blocking'])->toBeFalse()
        ->and($backlog['oldest']->name)->toBe('August 2026');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('messages.month_close_due_banner', ['month' => 'August 2026']))
        ->assertSee(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $backlog['oldest']->id]), false);

    // A reminder, not a gate.
    attemptWrite()->assertSessionMissing('error');
});

it('stops recording income and expenses once a second finished month is open', function () {
    $july = makeMonth(7);
    makeMonth(8);
    makeMonth(9);

    $backlog = app(MonthCloseBacklog::class)->build();
    expect($backlog['count'])->toBe(2)
        ->and($backlog['blocking'])->toBeTrue()
        ->and($backlog['oldest']->id)->toBe($july->id);

    // The write is refused before validation ever runs, and the admin lands on
    // the page that carries the close button.
    attemptWrite()
        ->assertRedirect(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $july->id]))
        ->assertSessionHas('error');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('messages.month_close_blocked_banner', [
            'count' => 2, 'months' => 'July 2026, August 2026', 'month' => 'July 2026',
        ]));
});

it('keeps every page readable while the block is on', function () {
    makeMonth(7);
    makeMonth(8);

    // Working out what a month owes is how the close gets done — the block is
    // on writes only.
    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income'))
        ->assertOk();

    $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.income_statement'))
        ->assertOk();
});

it('still lets a mistaken payment be reversed while the block is on', function () {
    makeMonth(7);
    makeMonth(8);

    $apartment = makeApartment(null, ['monthly_rent' => 500]);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, ['rent_amount' => 500, 'start_date' => '2026-05-10']);

    auth()->login($this->admin);
    (new IncomeRecordingService(userId: $this->admin->id, period: $this->period))
        ->checkout($rental, [
            'payment_date' => '2026-08-20',
            'payment_method' => 'cash',
            'rent_amount' => 500,
            'pay_rent' => true,
            'billing_month' => 8,
            'billing_year' => 2026,
        ]);
    auth()->logout();

    // Fixing August's books is a precondition of closing August, so blocking it
    // here would deadlock the very close being demanded.
    $rent = Payments::where('rental_id', $rental->id)->firstOrFail();
    $this->actingAs($this->admin)
        ->delete(route('admin.revenue_expense.reverse_payment', $rent))
        ->assertSessionHas('success');

    expect(Payments::find($rent->id))->toBeNull();
});

it('lifts the block as soon as the oldest month is closed', function () {
    $july = makeMonth(7);
    makeMonth(8);

    $this->actingAs($this->admin)
        ->post(route('admin.fiscalperiod.monthly-period.close', [$this->period->id, $july->id]), [])
        ->assertSessionHas('success');

    $backlog = app(MonthCloseBacklog::class)->build();
    expect($backlog['count'])->toBe(1)
        ->and($backlog['blocking'])->toBeFalse();

    attemptWrite()->assertSessionMissing('error');
});

it('lands the banner link on a page that actually offers the close', function () {
    $july = makeMonth(7);
    makeMonth(8);

    // The banner names the month and links here; the page has to carry the
    // action it promised, spelled out rather than hidden behind a padlock icon.
    auth()->login($this->admin);
    $backlog = app(MonthCloseBacklog::class)->build();
    auth()->logout();
    expect($backlog['close_url'])->toBe(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $july->id]));

    $this->actingAs($this->admin)
        ->get($backlog['close_url'])
        ->assertOk()
        ->assertSee(__('messages.close_month_now', ['month' => 'July 2026']))
        ->assertSee(route('admin.fiscalperiod.monthly-period.close', [$this->period->id, $july->id]), false);
});

it('offers the close to an account that has no properties at all', function () {
    $july = makeMonth(7);

    // The close freezes account-wide totals, so it is gated to the consolidated
    // view — but with nothing to consolidate, the only view IS that view. This
    // read as "not consolidated" and hid the button from every property-less
    // account, including the one the banner had just sent here.
    expect(\App\Models\Property::count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $july->id]))
        ->assertOk()
        ->assertSee(route('admin.fiscalperiod.monthly-period.close', [$this->period->id, $july->id]), false)
        ->assertDontSee(__('messages.month_close_needs_all_properties'));
});

it('explains the missing close button when one property of several is selected', function () {
    $july = makeMonth(7);

    auth()->login($this->admin);
    $a = \App\Models\Property::create(['name' => 'Building A']);
    \App\Models\Property::create(['name' => 'Building B']);
    auth()->logout();

    // Viewing one building of two: the close is out of scope, so the page says
    // why and offers the one click that fixes it instead of hiding in silence.
    $this->actingAs($this->admin)
        ->withSession([\App\Services\Property\PropertyContext::SESSION_KEY => $a->id])
        ->get(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $july->id]))
        ->assertOk()
        ->assertDontSee(route('admin.fiscalperiod.monthly-period.close', [$this->period->id, $july->id]), false)
        ->assertSee(__('messages.month_close_needs_all_properties'))
        ->assertSee(route('property.switch'), false);

    // ...and from the consolidated view it is there. PropertyContext memoizes
    // its lookups for the life of the instance — one request in production, but
    // the whole test here — so drop it before asking again under a new session.
    $this->app->forgetInstance(\App\Services\Property\PropertyContext::class);

    $this->actingAs($this->admin)
        ->withSession([\App\Services\Property\PropertyContext::SESSION_KEY => \App\Services\Property\PropertyContext::ALL_PROPERTIES])
        ->get(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, $july->id]))
        ->assertOk()
        ->assertSee(route('admin.fiscalperiod.monthly-period.close', [$this->period->id, $july->id]), false);
});

it('tells a supervisor to ask the owner instead of offering a close button', function () {
    makeMonth(7);
    makeMonth(8);

    $supervisor = makeSupervisor(['account_id' => $this->admin->id]);

    $this->actingAs($supervisor)
        ->get(route('supervisor.dashboard'))
        ->assertOk()
        ->assertSee(__('messages.month_close_ask_owner'))
        ->assertDontSee(route('admin.fiscalperiod.monthly-period.show', [$this->period->id, 1]), false);

    // Their writes land in the same books, so they stop too — bounced back
    // where they were, since they have no month-close page to be sent to.
    $this->actingAs($supervisor)
        ->from(route('supervisor.dashboard'))
        ->post(route('supervisor.revenue_expense.add_charge'), [])
        ->assertRedirect(route('supervisor.dashboard'))
        ->assertSessionHas('error');
});

it('ignores months whose fiscal period is already closed', function () {
    makeMonth(7);
    makeMonth(8);
    $this->period->update(['status' => 'closed']);

    auth()->login($this->admin);
    expect(app(MonthCloseBacklog::class)->build())->toBeNull();
    auth()->logout();
});

it('does not see another account\'s un-closed months', function () {
    makeMonth(7);
    makeMonth(8);

    $other = makeAdmin(['name' => 'Other Owner']);
    auth()->login($other);
    makeFiscalPeriod($other, ['opening_date' => '2026-01-01', 'closing_date' => '2026-12-31']);

    expect(app(MonthCloseBacklog::class)->build())->toBeNull();
    auth()->logout();
});
