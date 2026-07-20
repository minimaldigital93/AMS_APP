<?php

namespace App\Services\Contracts;

use App\Models\Rentals;
use App\Services\Pdf\KhmerPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the rental-contract PDF for a lease (a `rentals` row) and stores it on
 * the private disk. The single source of truth for the contract number, the
 * stored file name, and the data the contract Blade renders.
 *
 * Generation throws on failure — callers decide how to react:
 *   - the assignment flow swallows + logs (a failed PDF must never roll back a
 *     committed tenant assignment);
 *   - the "Regenerate" action surfaces a user-facing error flash.
 */
class ContractGenerator
{
    /** Private disk — maps to storage/app/private (config/filesystems.php). */
    public const DISK = 'local';

    /** Sub-directory under the private disk. */
    public const DIR = 'contracts';

    public function __construct(private KhmerPdf $pdf) {}

    /**
     * Ensure a contract number exists, render the PDF, store it, and stamp the
     * rental. Returns the stored path (relative to the private disk).
     */
    public function generate(Rentals $rental): string
    {
        $this->ensureContractNumber($rental);

        // mPDF, not the app-wide Dompdf — Dompdf cannot shape Khmer. See KhmerPdf.
        $bytes = $this->pdf->render('pdf.contract', $this->viewData($rental, forPdf: true));

        $path = $this->filePath($rental);
        Storage::disk(self::DISK)->put($path, $bytes);

        // Direct assignment bypasses $fillable (these columns are guarded on
        // purpose — only this service writes them).
        $rental->contract_path = $path;
        $rental->contract_generated_at = now();
        $rental->save();

        return $path;
    }

    /**
     * Assign a unique, human-readable contract number the first time one is
     * needed. Format: CTR-<year>-<zero-padded rental id>. The rental id is the
     * table primary key, so the number is globally unique by construction — no
     * counter table, no race — and never changes once set (regeneration keeps
     * the same number and overwrites the same file).
     */
    public function ensureContractNumber(Rentals $rental): string
    {
        if (filled($rental->contract_number)) {
            return $rental->contract_number;
        }

        $year = ($rental->created_at ?? Carbon::now())->year;
        $number = sprintf('CTR-%d-%06d', $year, $rental->id);

        $rental->contract_number = $number;
        $rental->save();

        return $number;
    }

    /** Stored path relative to the private disk, e.g. contracts/CTR-2026-000012.pdf. */
    public function filePath(Rentals $rental): string
    {
        return self::DIR.'/'.$this->downloadName($rental);
    }

    /** File name for downloads, e.g. CTR-2026-000012.pdf. */
    public function downloadName(Rentals $rental): string
    {
        $number = $this->ensureContractNumber($rental);

        return preg_replace('/[^A-Za-z0-9._-]/', '_', $number).'.pdf';
    }

    /**
     * Everything the contract Blade needs. Landlord (Party A) is read from the
     * account's owner settings; tenant + property come off the lease.
     *
     * @return array<string, mixed>
     */
    public function viewData(Rentals $rental, bool $forPdf = false): array
    {
        $rental->loadMissing(['tenant', 'apartment.floor.property', 'creator']);

        $tenant = $rental->tenant;
        $apartment = $rental->apartment;
        $floor = $apartment?->floor;
        $property = $apartment?->property; // accessor: floor->property

        return [
            'forPdf' => $forPdf,
            'rental' => $rental,
            'tenant' => $tenant,
            'apartment' => $apartment,
            'floor' => $floor,
            'property' => $property,
            'contractNumber' => $this->ensureContractNumber($rental),
            'generatedAt' => $rental->contract_generated_at ?? now(),
            'landlord' => $this->landlord(),
            'rates' => $this->rates($rental),
        ];
    }

    /**
     * Party "ក" on the contract. The owner is the natural person who signs, so
     * the form wants their gender and ID-card number as well as name/phone/
     * address. Those three fall back to the company block, which most accounts
     * fill in first and which is often the same person.
     *
     * @return array<string, mixed>
     */
    private function landlord(): array
    {
        return [
            'name' => settings('owner_name') ?: (settings('company_name') ?: config('app.name')),
            'gender' => settings('owner_gender'),
            'id_card' => settings('owner_id_card'),
            'phone' => settings('owner_phone') ?: settings('company_phone'),
            'address' => settings('owner_address') ?: settings('company_address'),
            'email' => settings('company_email'),
        ];
    }

    /**
     * The monthly charges printed in ប្រការ១, resolved per lease.
     *
     * A lease that carries its own price wins; otherwise the account-wide
     * default from Settings is used. Null means "neither is set" — the Blade
     * prints a dotted rule so the line can be completed by hand, exactly as the
     * paper form does.
     *
     * @return array<string, float|null>
     */
    private function rates(Rentals $rental): array
    {
        $resolve = function (string $column, ?string $settingKey = null) use ($rental): ?float {
            if ((float) $rental->{$column} > 0) {
                return (float) $rental->{$column};
            }

            $default = $settingKey ? (float) settings($settingKey) : 0.0;

            return $default > 0 ? $default : null;
        };

        return [
            'rent' => $resolve('rent_amount'),
            'electricity' => $resolve('electricity_price', 'utility_electricity_price'),
            'water' => $resolve('water_price', 'utility_water_price'),
            'parking' => $resolve('parking_fee', 'utility_parking_fee'),
            'internet' => $resolve('internet_fee', 'utility_internet_fee'),
            'garbage' => $resolve('garbage_fee', 'utility_garbage_fee'),
            'late' => $resolve('late_fee'),
        ];
    }
}
