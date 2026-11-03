<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingVoucher;
use App\Models\User;

/*
| SHIP-02 (docs/api/shipping.md): the shipping vouchers a customer may use on the checkout.
| ADM-30 – ADM-33 (docs/api/admin.md): the admin manages them.
| How ORD-01 applies a voucher is tested in CheckoutTest.
*/

beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
});

// --- SHIP-02 ---------------------------------------------------------------------------------------

test('SHIP-02 lists the running vouchers the customer still has uses of, free shipping first', function () {
    $discount = ShippingVoucher::factory()->create(['name' => 'Potongan 5rb', 'amount' => 5000, 'min_subtotal' => 100000, 'ends_at' => '2026-11-30 23:59:00']);
    $free = ShippingVoucher::factory()->freeShipping()->create(['name' => 'Gratis Ongkir', 'quota' => 10]);
    ShippingVoucher::factory()->create(['name' => 'Belum mulai', 'starts_at' => now()->addDay()]);
    ShippingVoucher::factory()->create(['name' => 'Sudah selesai', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subHour()]);
    $usedUp = ShippingVoucher::factory()->create(['name' => 'Kuota habis', 'quota' => 1]);
    Order::factory()->create(['shipping_voucher_id' => $usedUp->id, 'status' => OrderStatus::Paid]);
    $mine = ShippingVoucher::factory()->create(['name' => 'Sudah saya pakai', 'per_user_limit' => 1]);
    Order::factory()->for($this->customer)->create(['shipping_voucher_id' => $mine->id, 'status' => OrderStatus::Paid]);

    $this->actingAs($this->customer)->getJson('/api/v1/shipping/vouchers')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $free->id, 'name' => 'Gratis Ongkir', 'type' => 'free_shipping', 'type_label' => 'Gratis ongkir', 'amount' => null,
                'min_subtotal' => 0, 'ends_at' => $free->ends_at->toIso8601String(), 'remaining_quota' => 10],
            ['id' => $discount->id, 'name' => 'Potongan 5rb', 'type' => 'shipping_discount', 'type_label' => 'Potongan ongkir', 'amount' => 5000,
                'min_subtotal' => 100000, 'ends_at' => '2026-11-30T23:59:00+07:00', 'remaining_quota' => null],
        ]]);
});

test('SHIP-02 is for logged-in customers only', function () {
    assertApiError($this->getJson('/api/v1/shipping/vouchers'), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs($this->admin)->getJson('/api/v1/shipping/vouchers'), 403, 'ADMIN_CANNOT_SHOP');
});

// --- ADM-30 – ADM-33 -------------------------------------------------------------------------------

test('every voucher admin endpoint refuses guests and customers', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs($this->customer)->json($method, $uri), 403, 'FORBIDDEN');
})->with([
    ['GET', '/api/v1/admin/shipping-vouchers'],
    ['POST', '/api/v1/admin/shipping-vouchers'],
    ['PUT', '/api/v1/admin/shipping-vouchers/1'],
    ['DELETE', '/api/v1/admin/shipping-vouchers/1'],
]);

test('ADM-30 lists vouchers with their use and status, newest start first', function () {
    $running = ShippingVoucher::factory()->create(['name' => 'Aktif', 'starts_at' => now()->subDays(2), 'quota' => 5]);
    Order::factory()->create(['shipping_voucher_id' => $running->id, 'status' => OrderStatus::Paid]);
    Order::factory()->create(['shipping_voucher_id' => $running->id, 'status' => OrderStatus::Expired]);
    ShippingVoucher::factory()->create(['name' => 'Terjadwal', 'starts_at' => now()->addDay()]);
    ShippingVoucher::factory()->create(['name' => 'Berakhir', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeek()]);
    $exhausted = ShippingVoucher::factory()->create(['name' => 'Habis', 'starts_at' => now()->subDays(3), 'quota' => 1]);
    Order::factory()->create(['shipping_voucher_id' => $exhausted->id, 'status' => OrderStatus::PendingPayment]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/shipping-vouchers')->assertOk();

    expect($response->json('data.*.name'))->toBe(['Terjadwal', 'Aktif', 'Habis', 'Berakhir'])
        ->and(collect($response->json('data'))->pluck('status', 'name')->all())
        ->toBe(['Terjadwal' => 'scheduled', 'Aktif' => 'running', 'Habis' => 'exhausted', 'Berakhir' => 'ended'])
        ->and($response->json('data.1'))->toMatchArray(['used_count' => 1, 'quota' => 5, 'status_label' => 'Aktif'])
        ->and($response->json('meta.total'))->toBe(4);

    expect($this->actingAs($this->admin)->getJson('/api/v1/admin/shipping-vouchers?q=habis')->json('data.*.name'))->toBe(['Habis']);
});

test('ADM-31 creates a free-shipping voucher and ignores an amount sent with it', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/shipping-vouchers', [
        'name' => 'Gratis Ongkir', 'type' => 'free_shipping', 'amount' => 5000, 'min_subtotal' => 0,
        'starts_at' => '2026-11-04T00:00', 'ends_at' => '', 'quota' => '', 'per_user_limit' => '1',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'free_shipping')
        ->assertJsonPath('data.amount', null)
        ->assertJsonPath('data.ends_at', null)
        ->assertJsonPath('data.quota', null)
        ->assertJsonPath('data.per_user_limit', 1);

    // Typed as shop time (Asia/Jakarta).
    expect(ShippingVoucher::sole()->starts_at->toIso8601String())->toBe('2026-11-04T00:00:00+07:00');
});

test('ADM-31 checks the voucher', function (array $body, string $field) {
    $valid = ['name' => 'Potongan 5rb', 'type' => 'shipping_discount', 'amount' => 5000, 'min_subtotal' => 100000,
        'starts_at' => '2026-11-04T00:00', 'ends_at' => '2026-11-30T23:59', 'quota' => 100, 'per_user_limit' => 1];

    assertApiError($this->actingAs($this->admin)->postJson('/api/v1/admin/shipping-vouchers', $body + $valid), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
    expect(ShippingVoucher::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'unknown type' => [['type' => 'percent'], 'type'],
    'discount without amount' => [['amount' => null], 'amount'],
    'zero amount' => [['amount' => 0], 'amount'],
    'negative minimum' => [['min_subtotal' => -1], 'min_subtotal'],
    'ends before it starts' => [['ends_at' => '2026-11-03T00:00'], 'ends_at'],
    'zero quota' => [['quota' => 0], 'quota'],
    'zero per-user limit' => [['per_user_limit' => 0], 'per_user_limit'],
]);

test('ADM-32 changes a voucher; orders already placed keep their name and discount', function () {
    $voucher = ShippingVoucher::factory()->create(['name' => 'Lama']);
    $order = Order::factory()->create(['shipping_voucher_id' => $voucher->id, 'shipping_voucher_name' => 'Lama', 'discount' => 5000, 'status' => OrderStatus::Paid]);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/shipping-vouchers/{$voucher->id}", [
        'name' => 'Baru', 'type' => 'shipping_discount', 'amount' => 10000, 'min_subtotal' => 0,
        'starts_at' => '2026-11-01T00:00', 'ends_at' => '2026-12-01T00:00', 'quota' => null, 'per_user_limit' => null,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Baru')
        ->assertJsonPath('data.amount', 10000)
        ->assertJsonPath('data.used_count', 1);

    expect($order->refresh())->shipping_voucher_name->toBe('Lama')->discount->toBe(5000);
    assertApiError($this->actingAs($this->admin)->putJson('/api/v1/admin/shipping-vouchers/999', [
        'name' => 'Baru', 'type' => 'free_shipping', 'min_subtotal' => 0, 'starts_at' => '2026-11-01T00:00',
    ]), 404, 'VOUCHER_NOT_FOUND');
});

test('ADM-33 deletes an unused voucher and refuses one that orders used', function () {
    $unused = ShippingVoucher::factory()->create();
    $used = ShippingVoucher::factory()->create();
    Order::factory()->create(['shipping_voucher_id' => $used->id, 'status' => OrderStatus::Expired]);

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/shipping-vouchers/{$unused->id}")->assertOk()->assertExactJson(['message' => 'Voucher dihapus.']);
    assertApiError($this->actingAs($this->admin)->deleteJson("/api/v1/admin/shipping-vouchers/{$used->id}"), 409, 'VOUCHER_IN_USE');
    assertApiError($this->actingAs($this->admin)->deleteJson('/api/v1/admin/shipping-vouchers/999'), 404, 'VOUCHER_NOT_FOUND');

    expect(ShippingVoucher::pluck('id')->all())->toBe([$used->id]);
});

test('the voucher admin page renders', function () {
    $this->withoutVite();

    $this->actingAs($this->admin)->get('/admin/vouchers')->assertOk()->assertSee('Voucher Ongkir')->assertSee('x-data="adminVouchers"', false);
});
