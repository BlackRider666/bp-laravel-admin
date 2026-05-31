<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures the current request is authenticated under the
 * configured BPAdmin guard.
 *
 * Unauthenticated JSON requests receive a 401 response.
 * Unauthenticated browser requests are redirected to the login route.
 */
final class AdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('bpadmin.guard', 'web');

        if (!auth()->guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return to_route('bpadmin.auth.login');
        }

        return $next($request);
    }
}
