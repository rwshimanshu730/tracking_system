<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user()
            ?? auth('employee')->user()
            ?? auth('customer')->user();

        $allowed = false;

        if ($user && method_exists($user, 'hasRole')) {
            $allowed = $user->hasRole(...$roles);
        } elseif ($user && isset($user->role)) {
            $allowed = in_array($user->role, $roles, true);
        }

        if (! $allowed) {
            abort(403);
        }

        return $next($request);
    }
}
