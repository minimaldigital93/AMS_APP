<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rentals;
use App\Services\Contracts\ContractGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rental-contract PDF actions: preview, download, print and regenerate.
 *
 * Registered inside the admin route group (role:admin|superadmin +
 * subscription.active), and every action re-checks the RentalsPolicy. Rentals
 * are route-model-bound, so the account global scope already 404s a lease that
 * belongs to another account.
 *
 * PDF failures never surface a framework error page — they log the exception
 * and bounce back with a "unable to generate contract" flash.
 */
class ContractController extends Controller
{
    public function __construct(private ContractGenerator $contracts) {}

    /** Inline PDF preview (opens in the browser's PDF viewer / a new tab). */
    public function preview(Rentals $rental): Response|RedirectResponse
    {
        Gate::authorize('manageContract', $rental);

        if (! $this->ensurePdf($rental)) {
            return back()->with('error', __('messages.contract_generate_failed'));
        }

        return response(Storage::disk(ContractGenerator::DISK)->get($rental->contract_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->contracts->downloadName($rental).'"',
        ]);
    }

    /** Force-download the stored PDF. */
    public function download(Rentals $rental): StreamedResponse|RedirectResponse
    {
        Gate::authorize('manageContract', $rental);

        if (! $this->ensurePdf($rental)) {
            return back()->with('error', __('messages.contract_generate_failed'));
        }

        return Storage::disk(ContractGenerator::DISK)->download(
            $rental->contract_path,
            $this->contracts->downloadName($rental)
        );
    }

    /**
     * Browser-print view: renders the same contract template as HTML with the
     * web font path and an auto-print trigger, so the browser prints at full
     * fidelity (and can "Save as PDF" too).
     */
    public function print(Rentals $rental): Response
    {
        Gate::authorize('manageContract', $rental);

        return response()->view('pdf.contract', $this->contracts->viewData($rental, forPdf: false) + ['autoPrint' => true]);
    }

    /** Regenerate the PDF from current data, keeping the same contract number. */
    public function regenerate(Rentals $rental): RedirectResponse
    {
        Gate::authorize('manageContract', $rental);

        try {
            $this->contracts->generate($rental);
        } catch (\Throwable $e) {
            Log::error('Contract regeneration failed', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);

            return back()->with('error', __('messages.contract_generate_failed'));
        }

        return back()->with('success', __('messages.contract_regenerated'));
    }

    /**
     * Make sure a current PDF exists on disk, generating it on demand when it is
     * missing (e.g. an old lease, or a generation that failed at assignment
     * time). Returns false on failure so callers can flash instead of 500ing.
     */
    private function ensurePdf(Rentals $rental): bool
    {
        if ($rental->hasContract()) {
            return true;
        }

        try {
            $this->contracts->generate($rental);

            return true;
        } catch (\Throwable $e) {
            Log::error('Contract generation failed', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
