<?php

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Models\Order;
use App\Services\Shop\OrderNumberGenerator;
use App\Services\Shop\OrderStatusRecorder;

beforeEach(function () {
    $this->recorder = app(OrderStatusRecorder::class);
});

test('an order number is WEB- and the id padded to six digits', function () {
    $order = Order::factory()->create(['order_number' => null]);
    $order->forceFill(['order_number' => null])->save();

    app(OrderNumberGenerator::class)->assign($order);

    expect($order->fresh()->order_number)->toBe(sprintf('WEB-%06d', $order->id))->toMatch('/^WEB-\d{6}$/');
});

test('start writes the first history row with the current status', function () {
    $order = Order::factory()->create();

    $this->recorder->start($order, OrderStatusSource::System);

    expect($order->statusHistories()->get())->toHaveCount(1)
        ->and($order->statusHistories->first())
        ->status->toBe(OrderStatus::PendingPayment)
        ->source->toBe(OrderStatusSource::System);
});

test('an allowed transition changes the order, applies the attributes, and writes history', function () {
    $order = Order::factory()->create();
    $this->recorder->start($order, OrderStatusSource::System);

    $changed = $this->recorder->transition($order, OrderStatus::Paid, OrderStatusSource::Gapura, 'Dibayar via VA', ['paid_at' => now()]);

    expect($changed)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->statusHistories->pluck('status')->all())->toBe([OrderStatus::PendingPayment, OrderStatus::Paid])
        ->and($order->statusHistories->last())->source->toBe(OrderStatusSource::Gapura)->note->toBe('Dibayar via VA');
});

test('a transition the lifecycle forbids changes nothing and writes no history', function (OrderStatus $from, OrderStatus $to) {
    $order = Order::factory()->status($from)->create();

    $changed = $this->recorder->transition($order, $to, OrderStatusSource::Jubelio);

    expect($changed)->toBeFalse()
        ->and($order->fresh()->status)->toBe($from)
        ->and($order->statusHistories()->count())->toBe(0);
})->with([
    'paid back to pending' => [OrderStatus::Paid, OrderStatus::PendingPayment],
    'shipped back to processing' => [OrderStatus::Shipped, OrderStatus::Processing],
    'completed to anything' => [OrderStatus::Completed, OrderStatus::Cancelled],
    'expired to paid' => [OrderStatus::Expired, OrderStatus::Paid],
    'cancelled to paid' => [OrderStatus::Cancelled, OrderStatus::Paid],
    'pending straight to shipped' => [OrderStatus::PendingPayment, OrderStatus::Shipped],
]);

test('the same transition twice only counts once', function () {
    $order = Order::factory()->create();

    $first = $this->recorder->transition($order, OrderStatus::Expired, OrderStatusSource::Gapura);
    $second = $this->recorder->transition($order, OrderStatus::Expired, OrderStatusSource::Gapura);

    expect($first)->toBeTrue()->and($second)->toBeFalse()
        ->and($order->statusHistories()->count())->toBe(1);
});

test('transition works on a stale model: it decides from the database row', function () {
    $stale = Order::factory()->create();
    Order::whereKey($stale->id)->update(['status' => OrderStatus::Expired->value]);

    expect($this->recorder->transition($stale, OrderStatus::Paid, OrderStatusSource::Gapura))->toBeFalse()
        ->and($stale->status)->toBe(OrderStatus::Expired);
});
