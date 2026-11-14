<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Carbon;

$promise = fn (OrderStatus $status, string $paidAt) => (new Order(['status' => $status, 'paid_at' => Carbon::parse($paidAt, 'Asia/Jakarta')]))->trackingPromise();

test('an order paid before 15.00 WIB gets its tracking number by 16.00 the same day', function () use ($promise) {
    expect($promise(OrderStatus::Paid, '2026-11-14 14:59'))->toBe('Resi akan dikirim selambat-lambatnya pukul 16.00 WIB (Sabtu, 14 November 2026).')
        ->and($promise(OrderStatus::Processing, '2026-11-14 00:10'))->toBe('Resi akan dikirim selambat-lambatnya pukul 16.00 WIB (Sabtu, 14 November 2026).');
});

test('an order paid at 15.00 WIB or later gets it by 16.00 the next day', function () use ($promise) {
    expect($promise(OrderStatus::Paid, '2026-11-13 15:00'))->toBe('Resi akan dikirim selambat-lambatnya besok pukul 16.00 WIB (Sabtu, 14 November 2026).')
        ->and($promise(OrderStatus::Paid, '2026-11-30 23:59'))->toBe('Resi akan dikirim selambat-lambatnya besok pukul 16.00 WIB (Selasa, 1 Desember 2026).');
});

test('the warehouse does not ship on Sunday: Saturday after 15.00 and all of Sunday get Monday', function () use ($promise) {
    expect($promise(OrderStatus::Paid, '2026-11-14 15:00'))->toBe('Resi akan dikirim selambat-lambatnya hari Senin pukul 16.00 WIB (Senin, 16 November 2026).')
        ->and($promise(OrderStatus::Paid, '2026-11-15 09:00'))->toBe('Resi akan dikirim selambat-lambatnya besok pukul 16.00 WIB (Senin, 16 November 2026).')
        ->and($promise(OrderStatus::Paid, '2026-11-15 20:00'))->toBe('Resi akan dikirim selambat-lambatnya besok pukul 16.00 WIB (Senin, 16 November 2026).');
});

test('the cutoff is counted in WIB, whatever timezone the payment time comes in', function () {
    // 07:30 UTC is 14:30 WIB.
    $order = new Order(['status' => OrderStatus::Paid, 'paid_at' => Carbon::parse('2026-11-14 07:30', 'UTC')]);

    expect($order->trackingPromise())->toBe('Resi akan dikirim selambat-lambatnya pukul 16.00 WIB (Sabtu, 14 November 2026).');
});

test('there is no promise before payment or once the order has shipped', function (OrderStatus $status) use ($promise) {
    expect($promise($status, '2026-11-14 10:00'))->toBeNull();
})->with([OrderStatus::PendingPayment, OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Expired]);
