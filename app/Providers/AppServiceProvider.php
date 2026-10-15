<?php

namespace App\Providers;

use App\Services\Jubelio\JubelioClient;
use App\Services\JubelioShipment\JubelioShipmentClient;
use App\Services\Xendit\XenditClient;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JubelioClient::class, fn () => new JubelioClient(config('jubelio')));
        $this->app->singleton(JubelioShipmentClient::class, fn () => new JubelioShipmentClient(config('jubelio_shipment')));
        $this->app->singleton(XenditClient::class, fn () => new XenditClient(config('xendit')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Contract date format: ISO 8601 in WIB, e.g. "2026-10-14T21:30:00+07:00"
        // (docs/api/README.md "Aturan data"), for every date in a JSON response.
        Date::serializeUsing(fn (CarbonInterface $date) => $date->copy()
            ->setTimezone(config('app.timezone'))
            ->toIso8601String());
    }

    /**
     * Limits from docs/api/README.md "Batas request". Counters live in the
     * `database` cache store (no Redis).
     */
    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));

        // Counted per endpoint: failing login 5 times must not also block forgot-password.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by($request->path().'|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('shipping', fn (Request $request) => Limit::perMinute(20)->by($byUserOrIp($request)));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)->by($byUserOrIp($request)));
    }
}
