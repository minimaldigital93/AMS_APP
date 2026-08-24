<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use App\Services\Billing\BillingCycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rentals extends Model
{
    use BelongsToAccount, FiltersByProperty, SoftDeletes;

    /** Rentals reach a property through apartment → floor. */
    protected function propertyPath(): ?string
    {
        return 'apartment.floor';
    }

    protected $fillable = [
        'apartment_id',
        'tenant_id',
        'contract_number',
        'start_date',
        'end_date',
        'rent_amount',
        'electricity_price',
        'water_price',
        'parking_fee',
        'internet_fee',
        'garbage_fee',
        'late_fee',
        'payment_due_day',
        'contract_term_months',
        'deposit',
        'created_by',
    ];

    // contract_path / contract_generated_at are system-managed (written only by
    // the ContractGenerator), so they stay out of $fillable on purpose.

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'float',
            'electricity_price' => 'float',
            'water_price' => 'float',
            'parking_fee' => 'float',
            'internet_fee' => 'float',
            'garbage_fee' => 'float',
            'late_fee' => 'float',
            'payment_due_day' => 'integer',
            'contract_term_months' => 'integer',
            'deposit' => 'float',
            'contract_generated_at' => 'datetime',
        ];
    }

    // Relationships

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'rental_id');
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utilities::class, 'rental_id');
    }

    // Scopes

    /**
     * Only rentals that are currently active (no end_date, or end_date is in the future).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', now());
        });
    }

    // Contract helpers

    /** True once a contract PDF has been generated and is on disk. */
    public function hasContract(): bool
    {
        return filled($this->contract_path)
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->contract_path);
    }

    // Contract term (fixed 3/6/12-month lease agreed at assignment)

    /**
     * The date the agreed lease term runs out: start_date + contract_term_months.
     * Null when no start date or no fixed term was recorded (open-ended lease).
     */
    public function contractEndDate(): ?Carbon
    {
        if (! $this->start_date || ! $this->contract_term_months) {
            return null;
        }

        return Carbon::parse($this->start_date)->addMonthsNoOverflow($this->contract_term_months)->startOfDay();
    }

    /**
     * True once a fixed-term contract's end date has passed while the lease is
     * still open (no move-out) — i.e. the term lapsed and needs renewing.
     */
    public function contractIsOverdue(): bool
    {
        $end = $this->contractEndDate();

        return $end !== null && $this->end_date === null && $end->isPast();
    }

    /** Whole months the fixed-term contract is overdue by (0 when not overdue). */
    public function contractMonthsOverdue(): int
    {
        $end = $this->contractEndDate();

        if ($end === null || ! $this->contractIsOverdue()) {
            return 0;
        }

        return (int) $end->diffInMonths(now());
    }

    /**
     * Renew the fixed lease term by $months. The new term is added on from
     * wherever the current term ends — or from today when the lease is
     * open-ended or the term has already lapsed — and is stored back as whole
     * months from start_date, so contractEndDate()'s "start_date + term" model
     * stays intact. Persists the new term and returns it.
     */
    public function renewTerm(int $months): int
    {
        $start = $this->start_date
            ? Carbon::parse($this->start_date)->startOfDay()
            : now()->startOfDay();

        $currentEnd = $this->contractEndDate();
        $base = $currentEnd && $currentEnd->isFuture() ? $currentEnd->copy() : now()->startOfDay();
        $newEnd = $base->addMonthsNoOverflow($months);

        $this->contract_term_months = max(1, (int) round($start->diffInMonths($newEnd)));
        $this->save();

        return $this->contract_term_months;
    }

    // Derived attributes

    /**
     * Stay/tenure figures for the rolling monthly lease. The lease auto-renews
     * each month while the unit stays occupied, so rather than a fixed end date
     * we track progress through the *current* rental month.
     *
     * Which month that is belongs to `BillingCycleService`, not to this model:
     * when the account nominates a rent collection day every tenant's rental
     * month runs collection day → collection day and they all renew together.
     * This used to anchor on each tenant's own move-in anniversary, so with a
     * collection day of the 1st five tenants read five different renewal dates
     * and an arc that restarted mid-month — a tenant who moved in on the 22nd
     * showed 6% and "29 days left" on the 24th, while his August rent was in
     * fact 74% through and due in 8 days, and the gauge's amber/rose "renewal
     * imminent" colour fired on the wrong date for everyone. The first month is
     * short (move-in → the following collection day) and reads as such.
     *
     * No collection day set ⇒ `periodFor()` returns null and the original
     * move-in-anniversary cycle stands — the same backward-compatibility seam
     * every other billing caller keeps.
     *
     * Every figure here describes the CURRENT rental month and nothing else —
     * `cycle_label` is the day of that month ("24/31"), so it resets with the
     * arc on every collection day. It is deliberately not the tenancy's total
     * length: a cumulative "3 mo" in the gauge centre made a monthly gauge read
     * as a tenure counter, which is a different question (and one nobody
     * collecting this month's rent is asking). Neither is it time lived — the
     * cycle is the billing month, so a tenant who moved in mid-cycle is on day
     * N of the month's days, not day N of their stay.
     *
     * Single source of truth for rental-cycle figures across the app (tenant
     * index, floor plan, floors/apartments lists).
     *
     * @return array{cycle_percent: int|null, days_left: int|null, next_renewal_label: string|null, cycle_label: string|null, cycle_day: int, cycle_days: int}
     */
    public function stayProgress(?BillingCycleService $cycles = null): array
    {
        if (! $this->start_date) {
            return [
                'cycle_percent' => null,
                'days_left' => null,
                'next_renewal_label' => null,
                'cycle_label' => null,
                'cycle_day' => 0,
                'cycle_days' => 0,
            ];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $now = now();
        if ($now->lt($start)) {
            $now = $start->copy();
        }

        // The rental month the tenant is currently in. Callers that already
        // hold the service pass it; the rest resolve it here (settings are
        // memoised per request, so this costs no extra queries).
        $cycles ??= app(BillingCycleService::class);
        $period = $cycles->periodFor($this, $now->month, $now->year);

        // `periodFor()` returns the period that *starts* in the given month —
        // exactly one does — which on any day before the collection day is
        // still in the future. Until the 28th of a day-28 account the tenant is
        // living in last month's period, so step back to it. The move-in month
        // never needs this: its period starts on the move-in date itself.
        if ($period && $period->start->copy()->startOfDay()->gt($now)) {
            $previous = $now->copy()->startOfMonth()->subMonthNoOverflow();
            $period = $cycles->periodFor($this, $previous->month, $previous->year) ?? $period;
        }

        if ($period) {
            $cycleStart = $period->start->copy()->startOfDay();
            $cycleEnd = $period->end->copy()->startOfDay();
        } else {
            // Legacy cycle: start_date + N whole months → +1 month. Use the
            // no-overflow variants so a start day of e.g. the 31st lands on
            // month-end rather than spilling into the next month.
            $cycleStart = $start->copy()->addMonthsNoOverflow((int) $start->diffInMonths($now));
            if ($cycleStart->gt($now)) {
                $cycleStart->subMonthNoOverflow();
            }
            $cycleEnd = $cycleStart->copy()->addMonthNoOverflow();
        }

        // If the tenant is actually leaving (a real end_date falls inside this
        // cycle), the cycle ends there instead of renewing.
        if ($this->end_date) {
            $end = Carbon::parse($this->end_date)->endOfDay();
            if ($end->lt($cycleEnd)) {
                $cycleEnd = $end;
            }
        }

        $cycleDays = max(1, (int) $cycleStart->diffInDays($cycleEnd));
        $elapsedDays = max(0, (int) $cycleStart->diffInDays($now));
        $cyclePercent = (int) min(round(($elapsedDays / $cycleDays) * 100), 100);
        $daysLeft = max(0, (int) $now->copy()->startOfDay()->diffInDays($cycleEnd->copy()->startOfDay()));

        // Where in THIS rental month the tenant is. Counted inclusively — the
        // collection day itself is day 1, the same way the tenant index counts
        // `days_stayed` — and capped so a cycle that has run out reads as its
        // own last day rather than one past it.
        $cycleDay = min($elapsedDays + 1, $cycleDays);

        return [
            'cycle_percent' => $cyclePercent,
            'days_left' => $daysLeft,
            'next_renewal_label' => $cycleEnd->format('M j'),
            'cycle_label' => $cycleDay.'/'.$cycleDays,
            'cycle_day' => $cycleDay,
            'cycle_days' => $cycleDays,
        ];
    }
}
