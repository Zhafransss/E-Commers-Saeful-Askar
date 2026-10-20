<?php

use App\Models\Address;
use App\Models\Order;

/*
| Black-box: ADDR-01..06 and REG-01..04 (docs/api/addresses-regions.md).
*/

beforeEach(function () {
    fakeJubelioRegions();
    $this->user = bbCustomer();
    $this->valid = [
        'label' => 'Rumah', 'recipient_name' => 'Budi Santoso', 'phone' => '081234567890', 'address_line' => 'Jl. Mawar No. 12 RT 01/02',
        'province_id' => '32', 'city_id' => '3216', 'district_id' => '321605', 'subdistrict_id' => '3216052001', 'post_code' => '17565',
    ];
    $this->post = fn (array $override = []) => $this->actingAs($this->user)->postJson('/api/v1/addresses', $override + $this->valid);
});

test('the first address is automatically the default; later ones are not unless asked', function () {
    ($this->post)()->assertCreated()->assertJsonPath('data.is_default', true);
    ($this->post)()->assertCreated()->assertJsonPath('data.is_default', false);
    $third = ($this->post)(['is_default' => true])->assertCreated()->assertJsonPath('data.is_default', true);

    expect($this->user->addresses()->where('is_default', true)->count())->toBe(1)
        ->and($this->user->addresses()->where('is_default', true)->value('id'))->toBe($third->json('data.id'));
});

test('field boundaries: recipient 2..100, address_line 10..255, post_code exactly 5 digits, phone format, label max 50', function (array $override, bool $ok) {
    $response = ($this->post)($override);

    $ok ? $response->assertCreated() : assertApiError($response, 422, 'VALIDATION_ERROR');
})->with([
    'recipient 1' => [['recipient_name' => 'A'], false],
    'recipient 2' => [['recipient_name' => 'Al'], true],
    'recipient 101' => [['recipient_name' => str_repeat('a', 101)], false],
    'line 9' => [['address_line' => str_repeat('a', 9)], false],
    'line 10' => [['address_line' => str_repeat('a', 10)], true],
    'line 255' => [['address_line' => str_repeat('a', 255)], true],
    'line 256' => [['address_line' => str_repeat('a', 256)], false],
    'post code 4 digits' => [['post_code' => '1756'], false],
    'post code 6 digits' => [['post_code' => '175655'], false],
    'post code letters' => [['post_code' => '17A65'], false],
    'phone invalid' => [['phone' => '12345'], false],
    'label 51' => [['label' => str_repeat('a', 51)], false],
    'label null' => [['label' => null], true],
    'missing region' => [['subdistrict_id' => null], false],
]);

test('regions must be real and consistent: unknown id, or a city of another province, is REGION_NOT_FOUND', function (array $override) {
    assertApiError(($this->post)($override), 422, 'REGION_NOT_FOUND');
    expect(Address::count())->toBe(0);
})->with([
    'unknown province' => [['province_id' => '99']],
    'city not in province' => [['city_id' => '3171']],
    'district not in city' => [['district_id' => '999999']],
    'subdistrict not in district' => [['subdistrict_id' => '1234567890']],
]);

test('at most 10 addresses per user; the 11th is ADDRESS_LIMIT_REACHED and others are unaffected', function () {
    foreach (range(1, 10) as $i) {
        ($this->post)()->assertCreated();
    }
    assertApiError(($this->post)(), 422, 'ADDRESS_LIMIT_REACHED');

    ($this->actingAs(bbCustomer())->postJson('/api/v1/addresses', $this->valid))->assertCreated();
});

test('the list is the caller\'s own, default first', function () {
    $mine = Address::factory()->for($this->user)->create(['label' => 'Lama', 'created_at' => now()->subDay()]);
    $default = Address::factory()->for($this->user)->default()->create(['label' => 'Utama', 'created_at' => now()->subDays(2)]);
    Address::factory()->create(['label' => 'Orang Lain']);

    $labels = collect($this->actingAs($this->user)->getJson('/api/v1/addresses')->assertOk()->json('data'))->pluck('label');
    expect($labels->all())->toBe(['Utama', 'Lama']);
});

test('IDOR: another user\'s address is 404 on show, update, delete and set-default, and is left untouched', function () {
    $theirs = Address::factory()->default()->create(['recipient_name' => 'Orang Lain']);

    assertApiError($this->actingAs($this->user)->getJson("/api/v1/addresses/{$theirs->id}"), 404, 'ADDRESS_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->putJson("/api/v1/addresses/{$theirs->id}", $this->valid), 404, 'ADDRESS_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->deleteJson("/api/v1/addresses/{$theirs->id}"), 404, 'ADDRESS_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/addresses/{$theirs->id}/default"), 404, 'ADDRESS_NOT_FOUND');

    expect($theirs->fresh()->recipient_name)->toBe('Orang Lain')->and($theirs->fresh()->is_default)->toBeTrue();
});

test('mass assignment: user_id and is_default smuggled in the body do not move an address to someone else', function () {
    $other = bbCustomer();
    $id = ($this->post)(['user_id' => $other->id])->assertCreated()->json('data.id');

    expect(Address::find($id)->user_id)->toBe($this->user->id)->and($other->addresses()->count())->toBe(0);
});

test('update replaces the fields and keeps one default; deleting the default promotes the newest remaining address', function () {
    $first = ($this->post)(['label' => 'A'])->json('data.id');
    $second = ($this->post)(['label' => 'B'])->json('data.id');

    $this->actingAs($this->user)->putJson("/api/v1/addresses/{$second}", ['label' => 'B2', 'is_default' => true] + $this->valid)
        ->assertOk()->assertJsonPath('data.label', 'B2')->assertJsonPath('data.is_default', true);
    expect(Address::find($first)->is_default)->toBeFalse();

    $this->actingAs($this->user)->deleteJson("/api/v1/addresses/{$second}")->assertOk();
    expect(Address::find($first)->is_default)->toBeTrue();
});

test('set-default switches the default and answers the whole list', function () {
    $first = ($this->post)()->json('data.id');
    $second = ($this->post)()->json('data.id');

    $response = $this->actingAs($this->user)->patchJson("/api/v1/addresses/{$second}/default")->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $second)['is_default'])->toBeTrue()
        ->and(collect($response->json('data'))->firstWhere('id', $first)['is_default'])->toBeFalse();
});

test('an address that already belongs to an order can still be deleted, and the order keeps its shipping snapshot', function () {
    $address = Address::factory()->for($this->user)->create();
    $order = Order::factory()->for($this->user)->create(['address_id' => $address->id, 'shipping_recipient_name' => 'Penerima Snapshot']);

    $this->actingAs($this->user)->deleteJson("/api/v1/addresses/{$address->id}")->assertOk();

    expect($order->fresh()->shipping_recipient_name)->toBe('Penerima Snapshot');
});

test('regions: provinces → cities → districts → subdistricts, each parent required and unknown parents give an empty list', function () {
    $provinces = $this->actingAs($this->user)->getJson('/api/v1/regions/provinces')->assertOk()->json('data');
    expect($provinces)->not->toBeEmpty()->and($provinces[0])->toHaveKeys(['id', 'name']);

    expect($this->actingAs($this->user)->getJson('/api/v1/regions/cities?province_id=32')->json('data'))->not->toBeEmpty();
    expect($this->actingAs($this->user)->getJson('/api/v1/regions/cities?province_id=99')->assertOk()->json('data'))->toBe([]);
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/regions/cities'), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/regions/districts'), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/regions/subdistricts'), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/regions/cities?province_id='.str_repeat('1', 11)), 422, 'VALIDATION_ERROR');
});

test('every address and region endpoint needs a login', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
})->with([['GET', '/api/v1/addresses'], ['POST', '/api/v1/addresses'], ['GET', '/api/v1/addresses/1'], ['PUT', '/api/v1/addresses/1'], ['DELETE', '/api/v1/addresses/1'],
    ['PATCH', '/api/v1/addresses/1/default'], ['GET', '/api/v1/regions/provinces'], ['GET', '/api/v1/regions/cities?province_id=32']]);
