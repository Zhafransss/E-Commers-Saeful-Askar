<?php

use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\JubelioShipment\JubelioShipmentClient;
use App\Services\Shop\ShippingRateService;
use Illuminate\Support\Facades\Cache;

/*
| SHIP-01 (docs/api/shipping.md). The driver is `stub`: sample rates in the contract shape.
*/

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->address = Address::factory()->for($this->user)->create();
    $this->bike = ProductVariant::factory()->for(Product::factory()->create(['package_weight' => 32000]))
        ->create(['price' => 6750000, 'available_qty' => 4, 'package_weight' => 32000]);
    $this->battery = ProductVariant::factory()->for(Product::factory()->create(['package_weight' => 9500]))
        ->create(['price' => 3400000, 'available_qty' => 5, 'package_weight' => 9500]);

    $this->rates = fn (array $body = []) => $this->actingAs($this->user)
        ->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id] + $body);
});

function cartHas(User $user, ProductVariant $variant, int $qty): void
{
    $user->cartItems()->create(['product_variant_id' => $variant->id, 'qty' => $qty]);
}

test('SHIP-01 answers origin, destination, package, rates cheapest first, and an expiry', function () {
    cartHas($this->user, $this->bike, 1);

    $response = ($this->rates)()->assertOk();

    expect($response->json('data.origin'))->toBe([
        'name' => 'Gudang Tambun Utara', 'district' => 'TAMBUN UTARA', 'city' => 'KAB. BEKASI', 'post_code' => '17566',
    ])
        ->and($response->json('data.destination'))->toBe([
            'address_id' => $this->address->id, 'district' => 'Tambun Utara', 'city' => 'KAB. BEKASI',
            'province' => 'JAWA BARAT', 'post_code' => '17565',
        ])
        ->and($response->json('data.package'))->toBe(['total_weight' => 32000, 'item_count' => 1, 'subtotal' => 6750000]);

    $costs = collect($response->json('data.rates'))->pluck('cost');
    expect($costs->all())->toBe($costs->sort()->values()->all())
        ->and($response->json('data.rates.0'))->toHaveKeys([
            'rate_id', 'courier_code', 'courier_name', 'service_code', 'service_name', 'service_type',
            'cost', 'insurance_cost', 'etd_min_days', 'etd_max_days', 'etd_text',
        ])
        ->and($response->json('data.rates.0.rate_id'))->toStartWith('rt_')
        ->and($response->json('data.expires_at'))->toEndWith('+07:00');
});

test('the weight is the sum of qty × variant weight, and a deactivated line is left out', function () {
    cartHas($this->user, $this->bike, 1);
    cartHas($this->user, $this->battery, 2);
    $gone = ProductVariant::factory()->for(Product::factory()->create(['is_active' => false]))->create(['package_weight' => 50000, 'price' => 1000000]);
    cartHas($this->user, $gone, 1);

    $package = ($this->rates)()->assertOk()->json('data.package');

    expect($package)->toBe(['total_weight' => 32000 + 2 * 9500, 'item_count' => 2, 'subtotal' => 6750000 + 2 * 3400000]);
});

test('every rate is stored for 30 minutes with the user, address, and cart it was made for', function () {
    cartHas($this->user, $this->bike, 1);
    $rate = ($this->rates)()->json('data.rates.0');
    $service = app(ShippingRateService::class);

    expect($service->resolve($rate['rate_id'], $this->user, $this->address))
        ->toMatchArray(['rate_id' => $rate['rate_id'], 'cost' => $rate['cost'], 'service_name' => $rate['service_name']]);

    $this->travel(31)->minutes();
    expect(fn () => $service->resolve($rate['rate_id'], $this->user, $this->address))->toThrow(ApiException::class);
});

test('a rate is refused for another user, another address, or a changed cart', function (string $change) {
    cartHas($this->user, $this->bike, 1);
    $rate = ($this->rates)()->json('data.rates.0');
    $service = app(ShippingRateService::class);

    [$user, $address] = match ($change) {
        'another user' => [User::factory()->create(), $this->address],
        'another address' => [$this->user, Address::factory()->for($this->user)->create()],
        'cart changed' => (function () {
            cartHas($this->user, $this->battery, 1);

            return [$this->user, $this->address];
        })(),
    };

    try {
        $service->resolve($rate['rate_id'], $user, $address);
        $this->fail('Expected RATE_EXPIRED');
    } catch (ApiException $e) {
        expect($e->errorCode())->toBe('RATE_EXPIRED');
    }
})->with(['another user', 'another address', 'cart changed']);

test('an unknown rate id is RATE_EXPIRED', function () {
    cartHas($this->user, $this->bike, 1);

    expect(fn () => app(ShippingRateService::class)->resolve('rt_nonsuch0', $this->user, $this->address))
        ->toThrow(ApiException::class, 'kedaluwarsa');
});

test('the same address and weight within 5 minutes do not ask the provider again', function () {
    cartHas($this->user, $this->bike, 1);
    $calls = 0;
    $this->app->instance(JubelioShipmentClient::class, new class(config('jubelio_shipment'), function () use (&$calls) {
        $calls++;
    }) extends JubelioShipmentClient
    {
        public function __construct(array $config, private readonly Closure $onRates)
        {
            parent::__construct($config);
        }

        public function rates(array $destination, int $weightGrams): array
        {
            ($this->onRates)();

            return parent::rates($destination, $weightGrams);
        }
    });

    $first = ($this->rates)()->json('data.rates');
    $second = ($this->rates)()->json('data.rates');

    expect($calls)->toBe(1)
        ->and(array_column($first, 'cost'))->toBe(array_column($second, 'cost'))
        // Cached answers, but each response gets its own rate ids.
        ->and($first[0]['rate_id'])->not->toBe($second[0]['rate_id']);
});

test('a different weight asks the provider again', function () {
    cartHas($this->user, $this->bike, 1);
    ($this->rates)()->assertOk();
    cartHas($this->user, $this->battery, 1);

    expect(Cache::has("shipping:quote:{$this->address->subdistrict_id}:32000"))->toBeTrue()
        ->and(($this->rates)()->json('data.package.total_weight'))->toBe(41500)
        ->and(Cache::has("shipping:quote:{$this->address->subdistrict_id}:41500"))->toBeTrue();
});

test('service_types narrows the rates; nothing left answers 422 NO_SHIPPING_SERVICE', function () {
    cartHas($this->user, $this->bike, 1);

    $cargo = ($this->rates)(['service_types' => ['cargo']])->assertOk()->json('data.rates');
    expect(collect($cargo)->pluck('service_type')->unique()->all())->toBe(['cargo']);

    // A 32 kg bike is too heavy for instant and regular couriers.
    assertApiError(($this->rates)(['service_types' => ['instant']]), 422, 'NO_SHIPPING_SERVICE')
        ->assertJsonPath('message', 'Belum ada kurir yang bisa mengirim ke alamat ini untuk berat 32 kg. Silakan hubungi toko.');
});

test('a nearby light parcel gets an instant courier, a far one does not', function () {
    cartHas($this->user, $this->battery, 1);

    $near = collect(($this->rates)()->json('data.rates'))->pluck('service_type');
    expect($near)->toContain('instant');

    $this->address->update(['province_id' => '51', 'city_id' => '5171']);
    Cache::flush();
    $far = collect(($this->rates)()->json('data.rates'))->pluck('service_type');
    expect($far)->not->toContain('instant');
});

test('an empty cart, or one with only deactivated lines, answers 422 CART_EMPTY', function () {
    assertApiError(($this->rates)(), 422, 'CART_EMPTY');

    $gone = ProductVariant::factory()->for(Product::factory()->create(['is_active' => false]))->create();
    cartHas($this->user, $gone, 1);
    assertApiError(($this->rates)(), 422, 'CART_EMPTY');
});

test('an address that is missing or belongs to someone else answers 404 ADDRESS_NOT_FOUND', function () {
    cartHas($this->user, $this->bike, 1);
    $other = Address::factory()->create();

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => $other->id]), 404, 'ADDRESS_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => 999999]), 404, 'ADDRESS_NOT_FOUND');
});

test('SHIP-01 validates the body', function () {
    cartHas($this->user, $this->bike, 1);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', []), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('address_id');
    assertApiError(($this->rates)(['service_types' => ['teleport']]), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('service_types.0');
});

test('SHIP-01 needs a login', function () {
    assertApiError($this->postJson('/api/v1/shipping/rates', ['address_id' => 1]), 401, 'UNAUTHENTICATED');
});

test('a provider that is not built yet answers 502 INTEGRATION_ERROR without a technical message', function () {
    cartHas($this->user, $this->bike, 1);
    config(['jubelio_shipment.driver' => 'live']);
    $this->app->forgetInstance(JubelioShipmentClient::class);

    assertApiError(($this->rates)(), 502, 'INTEGRATION_ERROR')
        ->assertJsonPath('message', 'Layanan sedang tidak bisa dihubungi. Coba lagi beberapa saat.');
});
