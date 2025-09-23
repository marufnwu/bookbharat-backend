<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if user has the specified role or super-admin role
        // super-admin has access to everything
        if (!$request->user()->hasRole($role) && !$request->user()->hasRole('super-admin')) {
            return response()->json(['error' => 'Access denied. Required role: ' . $role], 403);
        }

        return $next($request);
    }
}