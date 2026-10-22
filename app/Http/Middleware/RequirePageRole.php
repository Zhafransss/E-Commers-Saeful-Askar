<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side guard for Blade pages (account & admin).
 *
 * Skipped while VITE_API_MOCK=true so the frontend team can build these pages
 * against mocks without a real session. Pages hold no private data either way:
 * everything is fetched from the API, which enforces auth on its own.
 */
class RequirePageRole
{
    public function handle(Request $request, Closure $next, string $role = 'customer'): Response
    {
        if (config('app.api_mock')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        abort_if($role === 'admin' && ! $user->isAdmin(), 403);

        // The shop pages (cart, checkout, orders) are for customers; an admin goes back to the panel.
        if ($role === 'customer' && $user->isAdmin()) {
            return redirect()->route('admin.orders.index');
        }

        return $next($request);
    }
}
