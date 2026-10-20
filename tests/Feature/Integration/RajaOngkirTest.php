<?php

use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\RajaOngkir\DestinationResolver;
use App\Services\RajaOngkir\RajaOngkirClient;
use App\Services\RajaOngkir\RajaOngkirRates;
use App\Services\Shipping\RateProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\TestHandler;

/*
| RajaOngkir (Komerce, API V2): INT-RO-01 destination search, INT-RO-02 domestic cost, and how they become
| SHIP-01 rates. Responses are shaped as the docs describe; a live check needs RAJAONGKIR_API_KEY.
*/

beforeEach(function () {
    config([
        'shipping.driver' => 'rajaongkir',
        'shipping.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
        'shipping.rajaongkir.api_key' => 'rahasia-ongkir',
        'shipping.rajaongkir.origin_id' => '17473',
        'shipping.rajaongkir.couriers' => ['jne', 'jnt', 'sicepat', 'sap'],
    ]);
    $this->app->forgetInstance(RajaOngkirClient::class);
    $this->app->forgetInstance(RateProvider::class);
});

function fakeRajaOngkir(array $routes = []): void
{
    Http::fake($routes + [
        '*/destination/domestic-destination*' => Http::response(fixture('rajaongkir/destinations-satriajaya.json')),
        '*/calculate/domestic-cost' => Http::response(fixture('rajaongkir/domestic-cost.json')),
    ]);
}

function tambun(): Address
{
    return Address::factory()->create(['subdistrict_id' => '3216052001', 'subdistrict_name' => 'Satriajaya', 'district_name' => 'TAMBUN UTARA', 'city_name' => 'KAB. BEKASI', 'post_code' => '17565']);
}

// --- Client ----------------------------------------------------------------------------------

test('the destination search sends the key header and keyword, and reads the ids', function () {
    fakeRajaOngkir();

    $found = app(RajaOngkirClient::class)->searchDestinations('satriajaya tambun utara');

    expect($found)->toHaveCount(3)->and($found[0])->toBe([
        'id' => 17473, 'label' => 'SATRIAJAYA, TAMBUN UTARA, BEKASI, JAWA BARAT, 17565', 'subdistrict_name' => 'SATRIAJAYA',
        'district_name' => 'TAMBUN UTARA', 'city_name' => 'BEKASI', 'province_name' => 'JAWA BARAT', 'zip_code' => '17565',
    ]);
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->hasHeader('key', 'rahasia-ongkir')
        && str_starts_with($r->url(), 'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination')
        && $r['search'] === 'satriajaya tambun utara');
});

test('the cost request is form-encoded, in grams, with all couriers joined by a colon', function () {
    fakeRajaOngkir();

    $services = app(RajaOngkirClient::class)->domesticCost(17473, 9999, 32000, ['jne', 'jnt']);

    expect($services)->toHaveCount(5)->and($services[0])->toBe([
        'name' => 'Jalur Nugraha Ekakurir (JNE)', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 45000, 'etd' => '2-3 day',
    ]);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost'
        && $r->hasHeader('key', 'rahasia-ongkir') && str_contains($r->header('Content-Type')[0], 'application/x-www-form-urlencoded')
        && $r->body() === 'origin=17473&destination=9999&weight=32000&courier=jne%3Ajnt&price=lowest');
});

test('"cost not found" (HTTP 400, or 400 inside a 200) is an empty list, not a failure', function (callable $response) {
    Http::fake(['*' => $response()]);

    expect(app(RajaOngkirClient::class)->domesticCost(1, 2, 1000, ['jne']))->toBe([]);
})->with([
    'HTTP 400' => [fn () => Http::response(['meta' => ['message' => 'Cost not found', 'code' => 400, 'status' => 'error'], 'data' => null], 400)],
    'meta 400' => [fn () => Http::response(['meta' => ['message' => 'Cost not found', 'code' => 400, 'status' => 'error'], 'data' => null])],
]);

test('other failures are integration errors, retried once only for server errors', function () {
    Http::fake(['*' => Http::sequence()
        ->push(['meta' => ['message' => 'Unauthorized', 'code' => 401]], 401)
        ->push(['meta' => ['message' => 'oops', 'code' => 500]], 500)
        ->push(['meta' => ['message' => 'oops', 'code' => 500]], 500)]);

    $e = catchIntegration(fn () => app(RajaOngkirClient::class)->withinCustomerRequest()->domesticCost(1, 2, 1000, ['jne']));
    expect($e)->httpStatus->toBe(401)->retryable->toBeFalse()->providerMessage->toBe('Unauthorized')->service->toBe('rajaongkir');
    Http::assertSentCount(1);

    $e = catchIntegration(fn () => app(RajaOngkirClient::class)->withinCustomerRequest()->domesticCost(1, 2, 1000, ['jne']));
    expect($e)->httpStatus->toBe(500)->retryable->toBeTrue();
    Http::assertSentCount(3);
});

test('the key never appears in the log', function () {
    config(['logging.channels.integrations' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
    fakeRajaOngkir();

    app(RajaOngkirClient::class)->domesticCost(1, 2, 1000, ['jne']);

    expect(json_encode(array_map(fn ($r) => $r['message'].json_encode($r['context']), integrationLog()->getRecords())))->not->toContain('rahasia-ongkir');
});

// --- Destination ids -------------------------------------------------------------------------

test('the address is matched by subdistrict, district, city, and postal code, not by the first result', function () {
    fakeRajaOngkir();

    // "KAB. BEKASI" must not pick the KOTA BEKASI entry that also says SATRIAJAYA.
    expect(app(DestinationResolver::class)->forAddress(tambun()))->toBe(17473);
});

test('a found destination is remembered, so the same address is searched once', function () {
    fakeRajaOngkir();
    $address = tambun();

    app(DestinationResolver::class)->forAddress($address);
    app(DestinationResolver::class)->forAddress($address);

    Http::assertSentCount(1);
});

test('a place RajaOngkir does not know is NO_SHIPPING_SERVICE, never a guess', function () {
    fakeRajaOngkir(['*/destination/domestic-destination*' => Http::response(['meta' => ['code' => 200], 'data' => [
        ['id' => 1, 'label' => 'X', 'province_name' => 'JAWA BARAT', 'city_name' => 'BEKASI', 'district_name' => 'TAMBUN UTARA', 'subdistrict_name' => 'LAIN', 'zip_code' => '17565'],
    ]])]);

    try {
        app(DestinationResolver::class)->forAddress(tambun());
        $this->fail('Expected NO_SHIPPING_SERVICE');
    } catch (ApiException $e) {
        expect($e->errorCode())->toBe('NO_SHIPPING_SERVICE')->and($e->status())->toBe(422);
    }
});

test('the warehouse id comes from RAJAONGKIR_ORIGIN_ID, or is looked up from the origin address', function () {
    fakeRajaOngkir();
    expect(app(DestinationResolver::class)->origin())->toBe(17473);
    Http::assertNothingSent();

    config(['shipping.rajaongkir.origin_id' => null]);
    Cache::flush();
    expect(app(DestinationResolver::class)->origin())->toBe(17473);
    Http::assertSentCount(1);
});

// --- Rates -----------------------------------------------------------------------------------

test('rates are mapped to the SHIP-01 shape: kinds of service, days, and no free services', function () {
    fakeRajaOngkir();

    $rates = collect(app(RajaOngkirRates::class)->rates(tambun(), 32000))->keyBy(fn ($r) => $r['courier_code'].'-'.$r['service_code']);

    expect($rates->keys()->all())->toBe(['jne-REG', 'jne-JTR', 'jnt-EZ', 'sicepat-SAMEDAY'])
        ->and($rates['jne-REG'])->toBe([
            'courier_code' => 'jne', 'courier_name' => 'Jalur Nugraha Ekakurir (JNE)', 'service_code' => 'REG',
            'service_name' => 'Jalur Nugraha Ekakurir (JNE) REG', 'service_type' => 'regular', 'cost' => 45000, 'insurance_cost' => 0,
            'etd_min_days' => 2, 'etd_max_days' => 3, 'etd_text' => '2–3 hari',
        ])
        ->and($rates['jne-JTR'])->service_type->toBe('cargo')
        ->and($rates['jnt-EZ'])->etd_text->toBe('1 hari')
        ->and($rates['sicepat-SAMEDAY'])->service_type->toBe('same_day')->etd_min_days->toBeNull()->etd_text->toBe('Estimasi belum tersedia');
});

test('SHIP-01 end to end with RajaOngkir: rates, a rate id, and the weight in grams', function () {
    fakeRajaOngkir();
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->create(['subdistrict_id' => '3216052001', 'subdistrict_name' => 'Satriajaya', 'district_name' => 'TAMBUN UTARA', 'city_name' => 'KAB. BEKASI', 'post_code' => '17565']);
    $bike = ProductVariant::factory()->for(Product::factory()->create())->create(['price' => 6750000, 'available_qty' => 4, 'package_weight' => 32000]);
    $user->cartItems()->create(['product_variant_id' => $bike->id, 'qty' => 1]);

    $response = $this->actingAs($user)->postJson('/api/v1/shipping/rates', ['address_id' => $address->id])->assertOk();

    expect(collect($response->json('data.rates'))->pluck('cost')->all())->toBe([42500, 45000, 60000, 320000])
        ->and($response->json('data.rates.0.rate_id'))->toStartWith('rt_')
        ->and($response->json('data.package.total_weight'))->toBe(32000);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'calculate/domestic-cost') && str_contains($r->body(), 'weight=32000'));

    // Same address and weight again: RajaOngkir is not asked again (the free plan has a daily quota).
    Http::fake();
    $this->actingAs($user)->postJson('/api/v1/shipping/rates', ['address_id' => $address->id])->assertOk();
    Http::assertNothingSent();
});

test('a route with no services at all is NO_SHIPPING_SERVICE', function () {
    fakeRajaOngkir(['*/calculate/domestic-cost' => Http::response(['meta' => ['message' => 'Cost not found', 'code' => 400, 'status' => 'error'], 'data' => null], 400)]);
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->create(['subdistrict_id' => '3216052001', 'subdistrict_name' => 'Satriajaya', 'district_name' => 'TAMBUN UTARA', 'city_name' => 'KAB. BEKASI', 'post_code' => '17565']);
    $bike = ProductVariant::factory()->for(Product::factory()->create())->create(['price' => 1000, 'available_qty' => 4, 'package_weight' => 500]);
    $user->cartItems()->create(['product_variant_id' => $bike->id, 'qty' => 1]);

    assertApiError($this->actingAs($user)->postJson('/api/v1/shipping/rates', ['address_id' => $address->id]), 422, 'NO_SHIPPING_SERVICE');
});
