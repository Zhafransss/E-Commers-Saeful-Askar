<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TEMP-DEBUG: why a customer coming back from DANA arrives without a login. Remove with its two callers
 * (CheckoutPageController::finish and ApiExceptionRenderer) once the cause is known.
 * Logs only whether cookies were sent, never their values.
 */
class TempCookieDebug
{
    public static function log(string $where, Request $request): void
    {
        $referer = (string) $request->headers->get('referer');

        Log::warning("TEMP-DEBUG {$where}", [
            'url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'cookie_names' => array_keys($request->cookies->all()),
            'has_session_cookie' => $request->cookies->has((string) config('session.cookie')),
            'authenticated' => $request->user() !== null,
            'session_cookie_name' => config('session.cookie'),
            'sec_fetch_site' => $request->headers->get('sec-fetch-site'),
            'sec_fetch_mode' => $request->headers->get('sec-fetch-mode'),
            'sec_fetch_dest' => $request->headers->get('sec-fetch-dest'),
            'referer_host' => $referer === '' ? null : parse_url($referer, PHP_URL_HOST),
            'user_agent' => substr((string) $request->userAgent(), 0, 120),
        ]);
    }
}
