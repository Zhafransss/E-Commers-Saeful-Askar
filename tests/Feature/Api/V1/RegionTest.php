<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
| REG-01 – REG-04 (docs/api/addresses-regions.md), from Jubelio INT-JB-08.
*/

beforeEach(function () {
    fakeJubelioRegions();
    $this->actingAs(User::factory()->create());
});

test('REG-01 lists provinces as Region objects, sorted by name', function () {
    $this->getJson('/api/v1/regions/provinces')->assertOk()->assertExactJson(['data' => [
        ['id' => '36', 'name' => 'BANTEN'],
        ['id' => '31', 'name' => 'DKI JAKARTA'],
        ['id' => '32', 'name' => 'JAWA BARAT'],
    ]]);
});

test('REG-02 to REG-04 follow the chain down to the warehouse village', function () {
    $this->getJson('/api/v1/regions/cities?province_id=32')->assertExactJson(['data' => [
        ['id' => '3216', 'name' => 'KAB. BEKASI'],
        ['id' => '3275', 'name' => 'KOTA BEKASI'],
    ]]);

    $this->getJson('/api/v1/regions/districts?city_id=3216')
        ->assertJsonPath('data.2', ['id' => '321605', 'name' => 'Tambun Utara']);

    $this->getJson('/api/v1/regions/subdistricts?district_id=321605')->assertExactJson(['data' => [
        ['id' => '3216052001', 'name' => 'Satriajaya', 'post_code' => null],
        ['id' => '3216052006', 'name' => 'Srijaya', 'post_code' => null],
    ]]);
});

test('REG-04 fills post codes from RajaOngkir when the district and city are named', function () {
    config(['shipping.driver' => 'rajaongkir', 'shipping.rajaongkir.api_key' => 'k']);
    fakeJubelioRegions(['/api/v1/destination/domestic-destination' => Http::response(fixture('rajaongkir/destinations-satriajaya.json'))]);

    $this->getJson('/api/v1/regions/subdistricts?district_id=321605&district_name=Tambun%20Utara&city_name=KAB.%20BEKASI')
        ->assertOk()->assertExactJson(['data' => [
            ['id' => '3216052001', 'name' => 'Satriajaya', 'post_code' => '17565'],
            ['id' => '3216052006', 'name' => 'Srijaya', 'post_code' => '17564'],
        ]]);
});

test('REG-04 still answers when RajaOngkir fails', function () {
    config(['shipping.driver' => 'rajaongkir', 'shipping.rajaongkir.api_key' => 'k']);
    fakeJubelioRegions(['/api/v1/destination/domestic-destination' => Http::response([], 500)]);

    $this->getJson('/api/v1/regions/subdistricts?district_id=321605&district_name=Tambun%20Utara&city_name=KAB.%20BEKASI')
        ->assertOk()->assertJsonPath('data.0.post_code', null);
});

test('Jubelio is called without a slash before the query string', function () {
    $this->getJson('/api/v1/regions/cities?province_id=32')->assertOk();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/region/cities?province_id=32'));
});

test('an unknown parent gives an empty list, not an error', function (string $uri) {
    $this->getJson($uri)->assertOk()->assertExactJson(['data' => []]);
})->with([
    '/api/v1/regions/cities?province_id=99',
    '/api/v1/regions/districts?city_id=9999',
    '/api/v1/regions/subdistricts?district_id=999999',
    '/api/v1/regions/cities?province_id=abc',
]);

test('a non-numeric parent never reaches Jubelio', function () {
    $this->getJson('/api/v1/regions/cities?province_id=abc')->assertOk();

    expect(jubelioCalls())->toBeEmpty();
});

test('a missing parent answers 422 with the contract message', function (string $uri, string $field, string $message) {
    assertApiError($this->getJson($uri), 422, 'VALIDATION_ERROR')->assertJsonPath("errors.{$field}.0", $message);
})->with([
    ['/api/v1/regions/cities', 'province_id', 'Provinsi wajib dipilih.'],
    ['/api/v1/regions/districts', 'city_id', 'Kota/kabupaten wajib dipilih.'],
    ['/api/v1/regions/subdistricts', 'district_id', 'Kecamatan wajib dipilih.'],
]);

test('lists are cached: a second call within 30 days does not call Jubelio', function () {
    $this->getJson('/api/v1/regions/provinces')->assertOk();
    $this->getJson('/api/v1/regions/provinces')->assertOk();
    $this->getJson('/api/v1/regions/cities?province_id=32')->assertOk();
    $this->getJson('/api/v1/regions/cities?province_id=32')->assertOk();

    $regionCalls = collect(jubelioCalls())->filter(fn ($path) => str_starts_with($path, '/region/'));
    expect($regionCalls->values()->all())->toBe(['/region/provinces', '/region/cities']);
});

test('after 30 days the list is fetched again', function () {
    $this->getJson('/api/v1/regions/provinces')->assertOk();
    $this->travel(31)->days();
    $this->getJson('/api/v1/regions/provinces')->assertOk();

    expect(collect(jubelioCalls())->filter(fn ($path) => $path === '/region/provinces'))->toHaveCount(2);
});

test('when Jubelio fails, an older cached list is still served with 200', function () {
    $this->getJson('/api/v1/regions/provinces')->assertOk();
    $this->travel(31)->days();
    fakeJubelioRegions(['/region/provinces' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    $this->getJson('/api/v1/regions/provinces')->assertOk()->assertJsonCount(3, 'data');
});

test('when Jubelio fails and nothing is cached, 502 INTEGRATION_ERROR with the contract message', function () {
    fakeJubelioRegions(['/region/provinces' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    assertApiError($this->getJson('/api/v1/regions/provinces'), 502, 'INTEGRATION_ERROR')
        ->assertExactJson(['message' => 'Data wilayah sedang tidak bisa dimuat. Coba lagi beberapa saat.', 'code' => 'INTEGRATION_ERROR']);

    // Retried once while the customer waits, not more (INT-HDL).
    expect(collect(jubelioCalls())->filter(fn ($path) => $path === '/region/provinces'))->toHaveCount(2);
});
