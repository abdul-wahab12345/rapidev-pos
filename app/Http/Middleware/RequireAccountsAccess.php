<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccountsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->canAccessAccounts()) {
            if ($request->expectsJson() || $request->inertia()) {
                abort(403, 'Access denied. Your role does not have permission to view financial accounts.');
            }
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access the accounts module.');
        }

        return $next($request);
    }
}
