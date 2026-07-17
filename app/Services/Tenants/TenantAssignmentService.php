<?php

namespace App\Services\Tenants;

use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one assign-tenant implementation shared by the Admin and Supervisor
 * panels (the controllers differ only in which fiscal period they resolve and
 * where they redirect).
 *
 * Everything runs in a single DB transaction, and every file written to disk is
 * tracked so a failure at ANY later step deletes the uploads along with the
 * rolled-back rows — no partial data, no orphaned files. Expected failures
 * surface as AssignTenantException with a translated, user-facing message.
 */
class TenantAssignmentService
{
    /** @var array<int, array{disk: string, path: string}> files written this run, deleted again on failure */
    private array $storedFiles = [];

    public function __construct(private AttachmentService $attachments) {}

    /**
     * @param  array<string, mixed>  $data  validated AssignTenantRequest data
     * @param  ?FiscalPeriods  $activePeriod  open period to book the deposit into (null = skip booking)
     *
     * @throws AssignTenantException on expected, user-facing failures
     */
    public function assign(Apartments $apartment, array $data, ?UploadedFile $photo, ?UploadedFile $idDocument, ?FiscalPeriods $activePeriod): void
    {
        $this->storedFiles = [];

        try {
            DB::transaction(function () use ($apartment, $data, $photo, $idDocument, $activePeriod) {
                // Lock the apartment row so two concurrent assignments can't both succeed.
                $apartment = Apartments::whereKey($apartment->id)->lockForUpdate()->firstOrFail();

                if ($apartment->status !== 'available') {
                    throw new AssignTenantException(__('messages.flash_apartment_not_available'));
                }

                $photoPath = $photo ? $this->storePhoto($photo) : null;

                if ($data['tenant_option'] === 'existing') {
                    $tenant = Tenants::whereNull('archived_at')->findOrFail($data['tenant_id']);

                    // An existing tenant must be unhoused — assigning one who still
                    // occupies another room would leave that room flagged occupied
                    // with an open rental while the tenant lives elsewhere. Moving
                    // rooms goes through the tenant edit flow, which ends the old
                    // rental and frees the old room in one transaction.
                    if ($tenant->apartment_id !== null) {
                        throw new AssignTenantException(__('messages.flash_tenant_already_housed'));
                    }

                    $this->attachTenantLogin($tenant);
                } else {
                    $tenant = $this->createTenantWithLogin($data, $photoPath, $apartment);
                }

                $tenant->update(array_filter([
                    'apartment_id' => $apartment->id,
                    'move_in_date' => $data['move_in_date'],
                    'deposit' => $data['deposit'],
                    'status' => 'active',
                    'managed_by' => Auth::id(),
                    'photo_path' => $photoPath,
                ], fn ($value) => $value !== null));

                $apartment->update(['status' => 'occupied']);

                if ($idDocument) {
                    $this->storeIdDocument($tenant, $idDocument);
                }

                $rental = Rentals::create([
                    'apartment_id' => $apartment->id,
                    'tenant_id' => $tenant->id,
                    'start_date' => Carbon::parse($data['move_in_date']),
                    'end_date' => null,
                    'rent_amount' => $apartment->monthly_rent,
                    'deposit' => $data['deposit'],
                ]);

                $this->recordDepositIncome($apartment, $rental, (float) $data['deposit'], $activePeriod);
            });
        } catch (\Throwable $e) {
            $this->deleteStoredFiles();

            throw $e;
        }
    }

    private function storePhoto(UploadedFile $photo): string
    {
        try {
            $path = $photo->store('tenants', 'public');
        } catch (\Throwable $e) {
            Log::error('Tenant photo upload failed during assignment: '.$e->getMessage());

            throw new AssignTenantException(__('messages.flash_upload_failed'), previous: $e);
        }

        if (! is_string($path) || $path === '') {
            throw new AssignTenantException(__('messages.flash_upload_failed'));
        }

        $this->storedFiles[] = ['disk' => 'public', 'path' => $path];

        return $path;
    }

    private function storeIdDocument(Tenants $tenant, UploadedFile $idDocument): void
    {
        try {
            $stored = $this->attachments->storeMany($tenant, [$idDocument], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
        } catch (\Throwable $e) {
            Log::error('Tenant document upload failed during assignment: '.$e->getMessage());

            throw new AssignTenantException(__('messages.flash_upload_failed'), previous: $e);
        }

        foreach ($stored as $attachment) {
            $this->storedFiles[] = ['disk' => Attachment::DISK, 'path' => $attachment->path];
        }
    }

    /** @param array<string, mixed> $data */
    private function createTenantWithLogin(array $data, ?string $photoPath, Apartments $apartment): Tenants
    {
        $tenantUser = User::forceCreate([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Str::random(16),
            'account_id' => current_account_id(),
        ]);
        $tenantUser->assignRole('tenant');

        return Tenants::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'gender' => $data['gender'] ?? null,
            'id_card_number' => $data['id_card_number'] ?? null,
            'address' => $data['address'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'photo_path' => $photoPath,
            'apartment_id' => $apartment->id,
            'status' => 'active',
            'managed_by' => Auth::id(),
            'user_id' => $tenantUser->id,
        ]);
    }

    /**
     * Give an existing tenant a portal login. The users table is one global
     * login namespace, so the lookup is global: attach only a same-account row;
     * a phone held by another account's login can't be reused — the assignment
     * proceeds without a login (fix the tenant's phone, then re-attach).
     */
    private function attachTenantLogin(Tenants $tenant): void
    {
        if ($tenant->user_id || ! $tenant->phone) {
            return;
        }

        $existingUser = User::where('phone', $tenant->phone)->first();

        if ($existingUser && $existingUser->account_id !== current_account_id()) {
            Log::warning('Tenant login not created: phone belongs to another account\'s user', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        if (! $existingUser) {
            $existingUser = User::forceCreate([
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'password' => Str::random(16),
                'account_id' => current_account_id(),
            ]);
        }

        if (! $existingUser->hasRole('tenant')) {
            $existingUser->assignRole('tenant');
        }

        $tenant->update(['user_id' => $existingUser->id]);
    }

    /**
     * Book the security deposit as deposit income. Skipped (with a warning)
     * when no fiscal period is open — accounts.fiscal_period_id is NOT NULL,
     * so an unconditional write would 500 the whole assignment. Ledger rows
     * carry the period owner's user_id (one-ledger invariant: supervisor
     * writes land in the admin's books).
     */
    private function recordDepositIncome(Apartments $apartment, Rentals $rental, float $deposit, ?FiscalPeriods $activePeriod): void
    {
        if ($deposit <= 0) {
            return;
        }

        if (! $activePeriod) {
            Log::warning('No active fiscal period — deposit income not recorded on assignment', [
                'rental_id' => $rental->id,
                'deposit' => $deposit,
            ]);

            return;
        }

        $reference = 'deposit:rental:'.$rental->id;

        Accounts::firstOrCreate(
            ['reference_number' => $reference],
            [
                'fiscal_period_id' => $activePeriod->id,
                'property_id' => $apartment->property_id ?? $apartment->floor?->property_id,
                'payment_id' => null,
                'user_id' => $activePeriod->user_id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_DEPOSIT_INCOME,
                'description' => 'Security deposit — Apt '.($apartment->apartment_number ?? 'N/A'),
                'amount' => $deposit,
                'transaction_date' => now()->toDateString(),
                'note' => 'Initial deposit collected on tenant assignment',
                'reference_number' => $reference,
            ]
        );
    }

    private function deleteStoredFiles(): void
    {
        foreach ($this->storedFiles as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (\Throwable $e) {
                Log::warning('Could not clean up uploaded file after failed assignment', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $this->storedFiles = [];
    }
}
