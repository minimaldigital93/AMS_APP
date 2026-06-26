<?php

namespace App\Http\Middleware;

use App\Services\Property\PropertyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seeds the global active-property context once per authenticated web request,
 * so the selection is restored (from the session, or the user's remembered
 * last_property_id after a fresh login) before any controller or view reads it.
 *
 * Resolution is memoized in the PropertyContext singleton, so touching it here
 * costs nothing extra later in the request. Superadmins reading cross-account
 * data have no single property and simply resolve to null — harmless.
 */
class SetPropertyContext
{
    public function __construct(private PropertyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                $this->context->activeProperty();
            } catch (\Throwable $e) {
                // Never let context resolution break the request pipeline.
            }
        }

        return $next($request);
    }
}
