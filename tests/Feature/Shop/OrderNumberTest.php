<?php

use App\Models\Order;
use App\Models\User;
use App\Services\Shop\OrderNumberGenerator;

/*
| The order number is the Jubelio `ref_no` and the DANA `partnerReferenceNo`; the prefix keeps two installs that
| share one DANA or Jubelio account (the live site and a laptop) from using the same number.
*/

function numberedOrder(): Order
{
    $order = Order::factory()->for(User::factory()->create())->create(['order_number' => null]);

    return app(OrderNumberGenerator::class)->assign($order);
}

test('an order number is the prefix and the id, six digits', function () {
    config(['shop.order_prefix' => 'WEB']);

    $order = numberedOrder();

    expect($order->order_number)->toBe(sprintf('WEB-%06d', $order->id));
});

test('another install uses another prefix, so its numbers never meet the live site\'s', function () {
    config(['shop.order_prefix' => 'DEV']);

    $order = numberedOrder();

    expect($order->order_number)->toBe(sprintf('DEV-%06d', $order->id))->and($order->fresh()->order_number)->toBe($order->order_number);
});

test('the prefix is cleaned to letters and digits, upper case, at most eight, and falls back to WEB', function (?string $set, string $used) {
    config(['shop.order_prefix' => $set]);

    expect(numberedOrder()->order_number)->toStartWith($used.'-');
})->with([
    ['dev', 'DEV'],
    ['d-e v!', 'DEV'],
    ['abcdefghijkl', 'ABCDEFGH'],
    ['', 'WEB'],
    ['---', 'WEB'],
    [null, 'WEB'],
]);
