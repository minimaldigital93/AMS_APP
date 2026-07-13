<?php

namespace App\Services\Platform;

use App\Models\ApartmentFixedExpense;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Payments;
use App\Models\Property;
use App\Models\Rentals;
use App\Models\Settings;
use App\Models\TenantLeave;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Utilities;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a customer account and every row/file it owns.
 *
 * The naive `Floors::delete()` + "let the DB cascade" approach silently left
 * most of the tree behind: Floors/Tenants are SoftDeletes models, so their
 * delete() is an UPDATE and DB cascades never fire — and the financial-history
 * FKs are deliberately RESTRICT now. Everything is therefore deleted
 * explicitly, children first.
 *
 * What survives on purpose:
 *  - platform-side khqr_payments (subscription revenue): their rental_id is
 *    NULL, and subscription_id/user_id/fiscal_period_id are SET NULL FKs —
 *    superadmin finance reads them for platform P&L.
 *  - audit_logs (append-only platform record).
 */
class AccountPurgeService
{
    public function __construct(private AuditLogger $audit) {}

    public function purge(User $owner): void
    {
        $id = $owner->id;

        // Collect file paths before the rows disappear. Attachments live on the
        // PRIVATE disk (Attachment::DISK); photos/logos/KHQR images are public.
        $privateFiles = Attachment::withoutAccountScope()
            ->where('account_id', $id)->pluck('path');
        $files = Tenants::withoutAccountScope()->withTrashed()
            ->where('account_id', $id)->whereNotNull('photo_path')->pluck('photo_path');
        $merchant = MerchantPaymentSetting::forAccount($id);
        if ($merchant?->khqr_image_path) {
            $files->push($merchant->khqr_image_path);
        }
        $logo = Settings::withoutAccountScope()
            ->where('account_id', $id)->where('key', 'company_logo')->value('value');
        if (filled($logo)) {
            $files->push($logo);
        }

        // The audit row is written first (it must not be lost if file cleanup
        // fails) and AuditLogger never throws into the money path by design.
        $this->audit->record('account.purged', $owner, [
            'account_id' => $id,
            'account_name' => $owner->name,
            'files' => $files->count(),
        ]);

        DB::transaction(function () use ($id) {
            $rentalIds = Rentals::withoutAccountScope()->withTrashed()
                ->where('account_id', $id)->pluck('id');

            // 1. Financial history — RESTRICT children go first. Merchant-side
            //    KHQR rows (tenant rent payments) belong to the account; the
            //    platform's subscription rows have rental_id NULL and are kept.
            KhqrPayment::whereIn('rental_id', $rentalIds)->delete();
            Payments::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            Utilities::withoutAccountScope()->where('account_id', $id)->delete();
            TenantLeave::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            ApartmentFixedExpense::withoutAccountScope()->where('account_id', $id)->delete();
            Attachment::withoutAccountScope()->where('account_id', $id)->delete();

            // 2. Occupancy chain, children before parents (floors.property_id
            //    is RESTRICT, so floors must go before properties).
            Rentals::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            Tenants::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            Apartments::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            Floors::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();
            Property::withoutAccountScope()->withTrashed()->where('account_id', $id)->forceDelete();

            // 3. The books. Deleting fiscal periods cascades accounts,
            //    balance_sheets and monthly_periods at the DB level (hard
            //    delete — FiscalPeriods has no SoftDeletes).
            BusinessExpense::withoutAccountScope()->where('account_id', $id)->delete();
            FiscalPeriods::withoutAccountScope()->where('account_id', $id)->delete();
            Settings::withoutAccountScope()->where('account_id', $id)->delete();

            // 4. People. Members first, owner last — the owner delete cascades
            //    subscriptions and merchant_payment_settings.
            User::where('account_id', $id)->where('id', '!=', $id)->delete();
            User::whereKey($id)->delete();
        });

        // Best-effort file cleanup after the commit — a failed unlink must not
        // roll back the purge.
        if ($files->isNotEmpty()) {
            Storage::disk('public')->delete($files->unique()->all());
        }
        if ($privateFiles->isNotEmpty()) {
            Storage::disk(Attachment::DISK)->delete($privateFiles->unique()->all());
        }
    }
}
