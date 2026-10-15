<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| INT-JB-14 · `php artisan jubelio:setup`.
*/

beforeEach(function () {
    Cache::put('jubelio:token', 'token-ada', 3600);
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com',
        'jubelio.store_id' => '-1043',
        'jubelio.location_id' => 1,
        'jubelio.tax_id' => -1,
        'jubelio.webhook_signature_header' => 'X-Signature',
    ]);
});

function fakeJubelioSetupData(): void
{
    Http::fake([
        '*/marketplace/store/*' => Http::response(fixture('jubelio/stores.json')),
        '*/locations/*' => Http::response(fixture('jubelio/locations.json')),
        '*/taxes/*' => Http::response(fixture('jubelio/taxes.json')),
        '*/systemsetting/webhook' => Http::response(['status' => 'ok']),
    ]);
}

/**
 * Runs the command and returns [exit code, output lines without the dot leaders].
 *
 * @return array{0: int, 1: list<string>}
 */
function runJubelioSetup(): array
{
    $exitCode = Artisan::call('jubelio:setup');
    $lines = array_map(fn (string $line) => trim(preg_replace('/\s*\.{2,}\s*/', ' | ', $line)), explode("\n", Artisan::output()));

    return [$exitCode, $lines];
}

test('lists stores, warehouses, and taxes, and confirms the IDs in .env by name', function () {
    fakeJubelioSetupData();

    [$exitCode, $lines] = runJubelioSetup();

    expect($exitCode)->toBe(0)
        ->and(implode("\n", $lines))->toContain('Saeful Askar Official')->toContain('← toko internal')->toContain('PPN 11%')
        ->and($lines)->toContain('JUBELIO_STORE_ID = -1043 | Website Saeful Askar')
        ->toContain('JUBELIO_LOCATION_ID = 1 | Gudang Tambun Utara')
        ->toContain('JUBELIO_TAX_ID = -1 | No Tax');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'systemsetting'));
});

test('fails when an ID in .env does not exist in Jubelio or is empty', function () {
    fakeJubelioSetupData();
    config(['jubelio.location_id' => 99, 'jubelio.tax_id' => null]);

    [$exitCode, $lines] = runJubelioSetup();

    expect($exitCode)->toBe(1)
        ->and($lines)->toContain('JUBELIO_STORE_ID = -1043 | Website Saeful Askar')
        ->toContain('JUBELIO_LOCATION_ID = 99 | TIDAK DITEMUKAN di Jubelio')
        ->toContain('JUBELIO_TAX_ID | belum diisi');
});

test('--register-webhooks registers the four WH-02 – WH-05 URLs on the given domain', function () {
    fakeJubelioSetupData();

    $this->artisan('jubelio:setup', ['--register-webhooks' => true, '--url' => 'https://toko.example', '--force' => true])
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api2.jubelio.com/systemsetting/webhook'
        && $request->method() === 'POST'
        && $request['webhooks'] === [
            ['action' => 'update-product', 'url' => 'https://toko.example/webhooks/jubelio/product'],
            ['action' => 'update-price', 'url' => 'https://toko.example/webhooks/jubelio/price'],
            ['action' => 'update-qty', 'url' => 'https://toko.example/webhooks/jubelio/stock'],
            ['action' => 'update-salesorder', 'url' => 'https://toko.example/webhooks/jubelio/salesorder'],
        ]);
});

test('webhooks are not registered when the confirmation is declined, and localhost is warned about', function () {
    fakeJubelioSetupData();
    config(['app.url' => 'http://localhost:8000']);

    $this->artisan('jubelio:setup', ['--register-webhooks' => true])
        ->expectsOutputToContain('bukan HTTPS publik')
        ->expectsConfirmation('Daftarkan webhook ini ke Jubelio?', 'no')
        ->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'systemsetting'));
});

test('a Jubelio error is shown with its detail and fails the command', function () {
    Http::fake(['*' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    $this->artisan('jubelio:setup')
        ->expectsOutputToContain('[23505]')
        ->assertFailed();
});
