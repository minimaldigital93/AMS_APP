<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rentals;
use App\Services\Contracts\ContractGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(ContractGenerator::DISK);

        return $disk->download(
            $rental->contract_path,
            $this->contracts->downloadName($rental)
        );
    }

    public function view(Rentals $rental): Response|RedirectResponse
    {
        Gate::authorize('manageContract', $rental);

        if (! $this->ensurePdf($rental)) {
            return back()->with('error', __('messages.contract_generate_failed'));
        }

        return response()->view('pdf.contract_viewer', [
            'rental' => $rental,
            'contractNumber' => $this->contracts->ensureContractNumber($rental),
        ]);
    }

    public function regenerate(Request $request, Rentals $rental): RedirectResponse
    {
        Gate::authorize('manageContract', $rental);

        $data = $request->validate([
            'renew_months' => 'nullable|integer|in:3,6,12',
        ]);
        $renewMonths = $data['renew_months'] ?? null;

        try {
            if ($renewMonths) {
                $rental->renewTerm((int) $renewMonths);
            }
            $this->contracts->generate($rental);
        } catch (\Throwable $e) {
            Log::error('Contract regeneration failed', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);

            return back()->with('error', __('messages.contract_generate_failed'));
        }

        return back()->with('success', __($renewMonths ? 'messages.contract_renewed' : 'messages.contract_regenerated'));
    }

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
