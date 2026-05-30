<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! Auth::check()) {
            abort(401, 'Unauthenticated');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole($role)) {
            abort(403, 'Unauthorized: You do not have the required role.');
        }

        return $next($request);
    }
}
