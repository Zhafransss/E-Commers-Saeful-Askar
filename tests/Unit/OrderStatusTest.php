<?php

use App\Enums\OrderStatus;

test('order statuses only follow the transitions allowed by the contract', function () {
    expect(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Expired))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped))->toBeTrue()
        ->and(OrderStatus::Processing->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Processing))->toBeFalse()
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled))->toBeFalse()
        ->and(OrderStatus::Expired->canTransitionTo(OrderStatus::Paid))->toBeFalse();
});

test('every order status has an Indonesian label', function () {
    expect(OrderStatus::PendingPayment->label())->toBe('Menunggu pembayaran')
        ->and(collect(OrderStatus::cases())->map->label()->filter()->count())->toBe(count(OrderStatus::cases()));
});
