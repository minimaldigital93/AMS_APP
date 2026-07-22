<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Models\Concerns\FiltersByProperty;
use App\Models\Concerns\TracksFileSizes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenants extends Model
{
    use BelongsToAccount, FiltersByProperty, SoftDeletes, TracksFileSizes;

    /** Tenants reach a property through apartment → floor. */
    protected function propertyPath(): ?string
    {
        return 'apartment.floor';
    }

    protected $fillable = [
        'apartment_id',
        'user_id',
        'managed_by',
        'name',
        'gender',
        'email',
        'id_card_number',
        'phone',
        'address',
        'date_of_birth',
        'place_of_birth',
        'move_in_date',
        'move_out_date',
        'status',
        'deposit',
        'photo_path',
        'notes',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'move_in_date' => 'date',
            'move_out_date' => 'date',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
            'deposit' => 'float',
            'photo_size' => 'integer',
        ];
    }

    /** Upload path columns whose byte size is tracked (see TracksFileSizes). */
    protected function fileSizeColumns(): array
    {
        return [
            'photo_path' => 'photo_size',
        ];
    }

    // Relationships

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartments::class, 'apartment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rentals::class, 'tenant_id');
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utilities::class, 'tenant_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(TenantLeave::class, 'tenant_id');
    }

    /**
     * Month-by-month rent payment history for this tenant, newest first.
     *
     * Walks every rental from its start month to the current month (or the
     * rental's end month, whichever is earlier) and flags each month as paid
     * when a `rent` payment was recorded with a `paid_at` inside that month.
     * Unpaid past/current months are what the tenant view lets the user settle.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function paymentHistory(): \Illuminate\Support\Collection
    {
        $now = now();
        $history = collect();

        foreach ($this->rentals as $rental) {
            if (! $rental->start_date) {
                continue;
            }

            $cursor = $rental->start_date->copy()->startOfMonth();
            $end = $rental->end_date ? $rental->end_date->copy()->startOfMonth() : $now->copy()->startOfMonth();
            if ($end->gt($now)) {
                $end = $now->copy()->startOfMonth();
            }

            while ($cursor->lte($end)) {
                $month = $cursor->month;
                $year = $cursor->year;

                // All payments the tenant actually made in this month, regardless
                // of type (rent, utilities, charges, late fees, …).
                $monthPayments = $rental->payments
                    ->filter(fn ($p) => $p->paid_at
                        && $p->paid_at->month === $month
                        && $p->paid_at->year === $year);

                // The month counts as paid once rent for it has been settled.
                $rentPayment = $monthPayments->firstWhere('payment_type', 'rent');

                // Date to stamp on a catch-up payment: end of that month, but
                // never in the future (clamp the current month to today).
                $payDate = $cursor->copy()->endOfMonth();
                if ($payDate->gt($now)) {
                    $payDate = $now->copy();
                }

                $history->push([
                    'rental_id' => $rental->id,
                    'apartment' => $rental->apartment?->apartment_number,
                    'month' => $month,
                    'year' => $year,
                    'label' => $cursor->format('M Y'),
                    'rent_amount' => (float) $rental->rent_amount,
                    'paid' => (bool) $rentPayment,
                    'paid_at' => $rentPayment?->paid_at,
                    // Total of everything the tenant actually paid this month.
                    'amount_paid' => $monthPayments->isNotEmpty() ? (float) $monthPayments->sum('amount') : null,
                    'pay_date' => $payDate->toDateString(),
                ]);

                $cursor->addMonth();
            }
        }

        return $history
            ->sortByDesc(fn ($r) => $r['year'] * 100 + $r['month'])
            ->values();
    }

    /**
     * The tenant's total outstanding debt, carried forward across months.
     *
     * Combines two independently-derived sources (no stored "debt" rows):
     *   - Unpaid rent: months from paymentHistory() with no settled rent payment.
     *   - Unpaid utilities: open Utilities rows (paid_status = false) for any month.
     *
     * Both persist automatically until settled, so a charge left unpaid when a
     * month closes keeps showing here in every later month.
     *
     * @return array{
     *     rent_due: float,
     *     utilities_due: float,
     *     total_due: float,
     *     unpaid_months: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     unpaid_utilities: \Illuminate\Support\Collection<int, object>,
     * }
     */
    public function outstandingCharges(): array
    {
        $unpaidMonths = $this->paymentHistory()->where('paid', false)->values();
        $rentDue = (float) $unpaidMonths->sum('rent_amount');

        $unpaidUtilities = Utilities::whereIn('rental_id', $this->rentals->pluck('id'))
            ->where('paid_status', false)
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->get()
            ->map(fn (Utilities $u) => (object) [
                'id' => $u->id,
                'type' => $u->utility_type,
                'amount' => (float) $u->charge_amount,
                'billing_month' => (int) $u->billing_month,
                'billing_year' => (int) $u->billing_year,
                'label' => \Carbon\Carbon::create($u->billing_year, $u->billing_month)->format('M Y'),
            ]);

        $utilitiesDue = round((float) $unpaidUtilities->sum('amount'), 2);

        return [
            'rent_due' => round($rentDue, 2),
            'utilities_due' => $utilitiesDue,
            'total_due' => round($rentDue + $utilitiesDue, 2),
            'unpaid_months' => $unpaidMonths,
            'unpaid_utilities' => $unpaidUtilities,
        ];
    }
}
