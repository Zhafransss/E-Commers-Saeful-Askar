<?php

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;

/*
| Black-box: SHIP-01 (docs/api/shipping.md).
*/

beforeEach(function () {
    config(['shipping.driver' => 'stub']);
    $this->user = bbCustomer();
    $this->address = Address::factory()->for($this->user)->default()->create();
    $this->variant = ProductVariant::factory()->for(Product::factory()->create())->create(['price' => 500000, 'available_qty' => 9, 'package_weight' => 3000]);
});

test('rates come back cheapest first, in rupiah integers, with a stable shape', function () {
    $this->user->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 2]);

    $data = $this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id])->assertOk()->json('data');

    $costs = collect($data['rates'])->pluck('cost');
    expect($costs->all())->toBe($costs->sort()->values()->all())
        ->and($costs->every(fn ($c) => is_int($c) && $c > 0))->toBeTrue()
        ->and($data['package'])->toMatchArray(['total_weight' => 6000, 'item_count' => 1, 'subtotal' => 1000000]);
});

test('a heavier cart never gets a cheaper rate for the same service', function () {
    $light = $this->user->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 1]);
    $price = fn () => collect($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id])->json('data.rates'))->pluck('cost', 'service_code');
    $before = $price();
    $light->update(['qty' => 5]);
    $after = $price();

    foreach ($before as $code => $cost) {
        if (isset($after[$code])) {
            expect($after[$code])->toBeGreaterThanOrEqual($cost);
        }
    }
});

test('invalid input: missing/foreign/unknown address, bad service type, empty cart', function () {
    $this->user->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 1]);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', []), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => 'x']), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => Address::factory()->create()->id]), 404, 'ADDRESS_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id, 'service_types' => 'regular']), 422, 'VALIDATION_ERROR');

    $this->user->cartItems()->delete();
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id]), 422, 'CART_EMPTY');
});

test('rate quoting is rate limited per user (429 after 20 a minute)', function () {
    $statuses = collect(range(1, 22))->map(fn () => $this->actingAs($this->user)->postJson('/api/v1/shipping/rates', [])->getStatusCode());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
});
