<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\RejectMalformedJson;
use App\Http\Middleware\RequirePageRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Webhooks are called server-to-server by Gapura (DANA) and Jubelio: no session,
            // no CSRF. Each route verifies its own signature (docs/api/webhooks.md).
            Route::prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));

            // DANA only posts to the path /v1.0/debit/notify (ASPI format), so WH-01 lives outside /webhooks.
            Route::name('webhooks.')->group(base_path('routes/gapura.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Session cookie + CSRF for requests coming from our own Blade pages (Sanctum stateful).
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->api(prepend: [RejectMalformedJson::class]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'page.role' => RequirePageRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiExceptionRenderer::shouldRender($request)
        );
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
