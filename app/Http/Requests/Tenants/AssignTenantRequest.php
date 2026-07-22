<?php

namespace App\Http\Requests\Tenants;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Models\Apartments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the Assign Tenant form for both the admin and supervisor panels.
 *
 * Upload rules check three things per file: the sniffed content (`mimes`), the
 * client extension (`extensions` — blocks double-extension names whose content
 * happens to pass the sniff), and the size cap. Keep the caps and type lists in
 * sync with the client-side guards in
 * resources/views/shared/apartments/_assign-tenant-modal.blade.php.
 */
class AssignTenantRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ConvertsCurrencyInput;
    use ScopesToSupervisorProperties;

    public const PHOTO_MAX_KB = 5120; // 5 MB

    public const DOCUMENT_MAX_KB = 10240; // 10 MB

    /** HEIC/HEIF stay allowed: iPhones produce them, and the client-side
     *  compressor can only convert them to JPEG on browsers that decode HEIC. */
    public const PHOTO_TYPES = ['jpeg', 'jpg', 'png', 'webp', 'heic', 'heif'];

    public const DOCUMENT_TYPES = ['pdf', 'jpeg', 'jpg', 'png', 'webp', 'heic', 'heif'];

    /**
     * Runs before validation, preserving the old authorize-then-validate order:
     * supervisors may only assign into rooms of their assigned properties;
     * admins/superadmins pass (their account scope already isolates them).
     */
    public function authorize(): bool
    {
        $apartment = $this->route('apartment');

        return $apartment instanceof Apartments && $this->supervisorCanAccessApartment($apartment);
    }

    /**
     * @return array<int, string>
     */
    protected function moneyInputKeys(): array
    {
        return ['deposit'];
    }

    public function rules(): array
    {
        // Same bounds as the tenant store flow: tenants are adults (18+)
        // and move-in can't be backdated more than a few days.
        $minBirthDate = now()->subYears(18)->toDateString();
        $minMoveInDate = now()->subDays(3)->toDateString();

        $photoTypes = implode(',', self::PHOTO_TYPES);
        $documentTypes = implode(',', self::DOCUMENT_TYPES);

        return [
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => [
                'nullable',
                'required_if:tenant_option,existing',
                // exists() bypasses Eloquent global scopes, so scope by hand to
                // the current account (NULL = legacy/unowned, matching
                // BelongsToAccount) and to live, unarchived tenants — otherwise
                // a crafted id passes validation and 404s mid-assignment.
                Rule::exists('tenants', 'id')->where(
                    fn ($q) => $q
                        ->where(fn ($q) => $q->where('account_id', current_account_id())->orWhereNull('account_id'))
                        ->whereNull('deleted_at')
                        ->whereNull('archived_at')
                ),
            ],
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'phone' => [
                'nullable', 'required_if:tenant_option,new', 'string', 'max:20', 'regex:/^[0-9+\\-\\s()]+$/',
                // Tenants stay per-account; the users table is one GLOBAL login
                // namespace (users_phone_unique) — a per-account rule here would
                // pass validation and then 500 on the insert.
                Rule::unique('tenants', 'phone')
                    ->where('account_id', current_account_id())
                    ->whereNull('deleted_at'),
                Rule::unique('users', 'phone'),
            ],
            'gender' => 'nullable|in:male,female,other',
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'date_of_birth' => 'nullable|date|before_or_equal:'.$minBirthDate,
            'attached_photo' => "nullable|file|mimes:{$photoTypes}|extensions:{$photoTypes}|max:".self::PHOTO_MAX_KB,
            'id_pdf' => "nullable|file|mimes:{$documentTypes}|extensions:{$documentTypes}|max:".self::DOCUMENT_MAX_KB,
            'move_in_date' => 'required|date|after_or_equal:'.$minMoveInDate,
            'deposit' => 'required|numeric|min:0|max:99999999.99',
            // Fixed lease term chosen on the form (3/6/12 months); optional so an
            // open-ended tenancy is still allowed.
            'contract_term_months' => 'nullable|integer|in:3,6,12',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => __('messages.validation_phone_taken'),
            'phone.regex' => __('messages.phone_must_be_english'),
            'attached_photo.max' => __('messages.validation_photo_too_large', ['max' => '5 MB']),
            'attached_photo.mimes' => __('messages.validation_photo_type'),
            'attached_photo.extensions' => __('messages.validation_photo_type'),
            'attached_photo.file' => __('messages.validation_upload_broken'),
            'id_pdf.max' => __('messages.validation_document_too_large', ['max' => '10 MB']),
            'id_pdf.mimes' => __('messages.validation_document_type'),
            'id_pdf.extensions' => __('messages.validation_document_type'),
            'id_pdf.file' => __('messages.validation_upload_broken'),
        ];
    }
}
