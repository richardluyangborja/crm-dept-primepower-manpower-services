<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin,manager')
 * Superadmin bypasses all role checks.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->role === 'superadmin' || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden. Ask your manager for access.'], 403);
    }
}
