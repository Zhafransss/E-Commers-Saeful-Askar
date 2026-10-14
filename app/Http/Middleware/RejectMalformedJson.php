<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Laravel silently treats an unparsable JSON body as empty input, which would
 * surface as confusing VALIDATION_ERRORs. The contract wants 400 BAD_REQUEST.
 */
class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if ($request->isJson() && trim($body) !== '') {
            json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BadRequestHttpException;
            }
        }

        return $next($request);
    }
}
