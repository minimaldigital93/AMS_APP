<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Theme\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Theme Settings — lets a user browse the theme catalog and pick one.
 *
 * The choice is per-user (users.theme) so it survives logout/login, and a
 * mirror cookie is set so the login screen keeps the same look on this device.
 */
class ThemeController extends Controller
{
    public function __construct(private readonly ThemeService $themes) {}

    public function index(): View
    {
        return view('admin.settings.theme', [
            'themes' => $this->themes->catalog(),
            'active' => $this->themes->currentSlug(),
        ]);
    }

    /**
     * Persist the chosen theme. Responds with JSON for the live switcher
     * (fetch) and redirects back for a non-JS form fallback.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in($this->themes->catalog()->pluck('slug'))],
        ]);

        $theme = $this->themes->setForUser(Auth::user(), $validated['theme']);
        $cookie = $this->themes->mirrorCookie($theme->slug);

        if ($request->expectsJson()) {
            return response()
                ->json(['theme' => $theme->slug, 'name' => $theme->name])
                ->withCookie($cookie);
        }

        return redirect()
            ->route('admin.settings.theme')
            ->with('success', __('messages.theme_saved'))
            ->withCookie($cookie);
    }
}
