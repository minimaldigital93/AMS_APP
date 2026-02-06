<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            abort(401, 'Unauthenticated');
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasRole($role)) {
            abort(403, 'Unauthorized: You do not have the required role.');
        }

        return $next($request);
    }
}
