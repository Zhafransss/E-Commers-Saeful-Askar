<?php

namespace App\Providers;

use App\Services\Jubelio\JubelioClient;
use App\Services\JubelioShipment\JubelioShipmentClient;
use App\Services\Xendit\XenditClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
    }

    /**
     * Limits from docs/api/README.md "Batas request". Counters live in the
     * `database` cache store (no Redis).
     */
    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('shipping', fn (Request $request) => Limit::perMinute(20)->by($byUserOrIp($request)));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)->by($byUserOrIp($request)));
    }
}
