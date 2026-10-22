<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the shopping endpoints (cart, shipping rates, checkout): an admin account runs the shop and does
 * not buy from it, so it answers 403 ADMIN_CANNOT_SHOP. Must run after auth:sanctum.
 */
class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            throw new ApiException('Akun admin tidak dapat memakai keranjang atau checkout.', 'ADMIN_CANNOT_SHOP', 403);
        }

        return $next($request);
    }
}
