<?php

namespace App\Providers;

use App\Services\Gapura\GapuraClient;
use App\Services\Jubelio\JubelioClient;
use App\Services\Jubelio\JubelioTokenManager;
use App\Services\Jubelio\Mappers\ProductMapper;
use App\Services\Jubelio\StockSyncService;
use App\Services\RajaOngkir\RajaOngkirClient;
use App\Services\RajaOngkir\RajaOngkirRates;
use App\Services\Shipping\RateProvider;
use App\Services\Shipping\StubRateProvider;
use App\Support\HtmlSanitizer;
use App\Support\QueueKicker;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JubelioTokenManager::class, fn () => new JubelioTokenManager(
            Cache::store(),
            config('jubelio.token_ttl'),
            config('jubelio.login_failure_cooldown'),
        ));
        $this->app->singleton(JubelioClient::class, fn ($app) => new JubelioClient(
            config('jubelio'),
            $app->make(JubelioTokenManager::class),
        ));
        $this->app->bind(ProductMapper::class, fn () => new ProductMapper(
            new HtmlSanitizer,
            filled(config('jubelio.store_id')) ? (string) config('jubelio.store_id') : null,
        ));
        $this->app->bind(StockSyncService::class, fn ($app) => new StockSyncService(
            $app->make(JubelioClient::class),
            config('jubelio.location_id'),
        ));
        $this->app->singleton(RajaOngkirClient::class, fn () => new RajaOngkirClient(config('shipping.rajaongkir')));
        $this->app->bind(RateProvider::class, fn ($app) => match (config('shipping.driver')) {
            'rajaongkir' => $app->make(RajaOngkirRates::class),
            default => $app->make(StubRateProvider::class),
        });
        $this->app->singleton(GapuraClient::class, fn () => new GapuraClient(config('gapura')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Shared hosting: work the queue right after a web request that queued a job (see QueueKicker).
        Event::listen(JobQueued::class, fn () => QueueKicker::jobQueued());
        $this->app->terminating(fn () => QueueKicker::terminate());

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
            ->by($request->path().'|'.mb_strtolower(is_string($email = $request->input('email')) ? $email : '').'|'.$request->ip()));

        RateLimiter::for('shipping', fn (Request $request) => Limit::perMinute(20)->by($byUserOrIp($request)));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)->by($byUserOrIp($request)));
    }
}
