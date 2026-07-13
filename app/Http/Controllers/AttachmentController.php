<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Models\Attachment;
use App\Models\BusinessExpense;
use App\Models\Tenants;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authenticated download/preview endpoint for private attachments (tenant ID
 * documents, business-expense receipts). Files used to live on the public disk
 * — world-readable at /storage/… to anyone holding the URL — and were moved to
 * the private local disk by the 2026-07 security audit (G2); this route is now
 * the only way to read them.
 *
 * Authorization matrix:
 *  - admin / superadmin: any attachment in their account (the BelongsToAccount
 *    scope already 404s cross-account route bindings).
 *  - supervisor: only attachments whose subject lives in an assigned property —
 *    a tenant's current room, an archived tenant's leave-history rooms, or a
 *    business expense tagged to an assigned property (null = account-wide, allowed).
 *  - tenant: only documents attached to their own tenant record.
 */
class AttachmentController extends Controller
{
    use ScopesToSupervisorProperties;

    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($attachment);

        abort_unless(Storage::disk(Attachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(Attachment::DISK)->response(
            $attachment->path,
            $attachment->original_name,
        );
    }

    private function authorizeAttachment(Attachment $attachment): void
    {
        $user = Auth::user();
        $attachable = $attachment->attachable()->withTrashed()->first();

        if ($user->hasRole('tenant')) {
            abort_unless(
                $attachable instanceof Tenants && $attachable->user_id === $user->id,
                403
            );

            return;
        }

        // Admin/superadmin previewing any panel see the whole account.
        if ($this->seesWholeAccount()) {
            return;
        }

        abort_unless($user->hasRole('supervisor'), 403);

        if ($attachable instanceof Tenants) {
            $propertyIds = $this->supervisorPropertyIds();

            $tenantPropertyIds = collect([$attachable->apartment?->floor?->property_id])
                ->merge($attachable->leaves()->with('apartment.floor')->get()
                    ->map(fn ($leave) => $leave->apartment?->floor?->property_id))
                ->filter();

            abort_unless($tenantPropertyIds->intersect($propertyIds)->isNotEmpty(), 403);

            return;
        }

        if ($attachable instanceof BusinessExpense) {
            // Null property_id = account-wide row, visible to every panel actor.
            abort_unless(
                $attachable->property_id === null
                    || $this->supervisorPropertyIds()->contains($attachable->property_id),
                403
            );

            return;
        }

        abort(403);
    }
}
