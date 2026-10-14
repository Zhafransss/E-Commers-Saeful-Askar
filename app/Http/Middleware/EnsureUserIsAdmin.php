<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Guards /api/v1/admin/* (docs/api/admin.md): customers get 403 FORBIDDEN.
 * Must run after auth:sanctum so guests still get 401.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
