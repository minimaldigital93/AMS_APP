<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rentals extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'apartment_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'float',
            'deposit' => 'float',
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

    // Derived attributes

    /**
     * Stay/tenure figures for the rolling monthly lease. The lease term is one
     * month from the start day and auto-renews each month while the unit stays
     * occupied, so rather than a fixed end date we track progress through the
     * *current* monthly cycle (latest anniversary on/before today → next one).
     *
     * Single source of truth for stay figures across the app (tenant index,
     * floor plan, …).
     *
     * @return array{cycle_percent: int|null, days_left: int|null, next_renewal_label: string|null, stay_label: string|null, months_stayed: int}
     */
    public function stayProgress(): array
    {
        if (! $this->start_date) {
            return [
                'cycle_percent' => null,
                'days_left' => null,
                'next_renewal_label' => null,
                'stay_label' => null,
                'months_stayed' => 0,
            ];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $now = now();
        if ($now->lt($start)) {
            $now = $start->copy();
        }

        // Total tenure — the "stay duration" shown in the gauge centre.
        $monthsStayed = (int) $start->diffInMonths($now);

        // Current renewal cycle: start_date + N whole months → +1 month. Use the
        // no-overflow variants so a start day of e.g. the 31st lands on month-end
        // rather than spilling into the next month.
        $cycleStart = $start->copy()->addMonthsNoOverflow($monthsStayed);
        if ($cycleStart->gt($now)) {
            $cycleStart->subMonthNoOverflow();
        }
        $cycleEnd = $cycleStart->copy()->addMonthNoOverflow();

        // If the tenant is actually leaving (a real end_date falls inside this
        // cycle), the cycle ends there instead of renewing.
        if ($this->end_date) {
            $end = Carbon::parse($this->end_date)->endOfDay();
            if ($end->lt($cycleEnd)) {
                $cycleEnd = $end;
            }
        }

        $cycleDays = max(1, (int) $cycleStart->diffInDays($cycleEnd));
        $elapsedDays = (int) $cycleStart->diffInDays($now);
        $cyclePercent = (int) min(round(($elapsedDays / $cycleDays) * 100), 100);
        $daysLeft = max(0, (int) $now->copy()->startOfDay()->diffInDays($cycleEnd->copy()->startOfDay()));

        return [
            'cycle_percent' => $cyclePercent,
            'days_left' => $daysLeft,
            'next_renewal_label' => $cycleEnd->format('M j'),
            'stay_label' => $this->durationLabel($monthsStayed, $start, $now),
            'months_stayed' => $monthsStayed,
        ];
    }

    /**
     * Human-friendly tenure label, e.g. "1 yr 2 mo", "8 mo", "12 days".
     */
    private function durationLabel(int $months, Carbon $start, Carbon $end): string
    {
        if ($months < 1) {
            $days = (int) $start->diffInDays($end);

            return $days <= 1 ? '1 day' : "{$days} days";
        }

        $years = intdiv($months, 12);
        $rem = $months % 12;

        $parts = [];
        if ($years > 0) {
            $parts[] = "{$years} yr";
        }
        if ($rem > 0 || $years === 0) {
            $parts[] = "{$rem} mo";
        }

        return implode(' ', $parts);
    }
}
