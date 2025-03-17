<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string[]  ...$guards
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $next($request);
            }
        }

        // Handle unauthenticated requests directly
        return $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, array $guards): Response
    {
        Log::info('Unauthenticated called', ['path' => $request->path()]);
        if ($request->is('api/*')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // For non-API routes (if any), throw exception with no redirect
        throw new AuthenticationException('Unauthorized.', $guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo(Request $request)
    {
        // Only relevant for non-API routes; return null for API
        if ($request->is('api/*')) {
            return null;
        }

        return '/login'; // Adjust if you ever add web routes
    }
}