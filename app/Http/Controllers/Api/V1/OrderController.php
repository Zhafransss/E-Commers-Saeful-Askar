<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderDetailResource;
use App\Http\Resources\Api\V1\OrderSummaryResource;
use App\Models\Order;
use App\Services\Shop\OrderCancellationService;
use App\Services\Shop\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Contract: docs/api/checkout-orders.md
 */
class OrderController extends Controller
{
    /**
     * ORD-02 · Riwayat pesanan saya
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:48']]);

        $statuses = collect(explode(',', (string) $request->query('status')))->map(fn ($s) => trim($s))->filter()->values();
        $unknown = $statuses->first(fn ($s) => OrderStatus::tryFrom($s) === null);
        if ($unknown !== null) {
            throw ValidationException::withMessages(['status' => ["Status tidak dikenal: {$unknown}."]]);
        }

        $orders = $request->user()->orders()
            ->where('is_checkout_failed', false)
            ->when($statuses->isNotEmpty(), fn ($q) => $q->whereIn('status', $statuses->all()))
            ->with('items')
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return OrderSummaryResource::collection($orders)->response();
    }

    /**
     * ORD-03 · Detail pesanan
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        return (new OrderDetailResource($this->find($request, $orderNumber)->load(['items', 'payment', 'statusHistories'])))->response();
    }

    /**
     * ORD-04 · Lanjutkan pembayaran
     */
    public function payment(Request $request, string $orderNumber, OrderPaymentService $payments): JsonResponse
    {
        $order = $this->find($request, $orderNumber);

        // A late notification: ask DANA before telling the customer anything about this order.
        if ($order->status === OrderStatus::PendingPayment && $order->payment_expires_at?->isPast()) {
            try {
                $payments->reconcile($order);
            } catch (\Throwable) {
                // Unreachable: judge by what we know.
            }
            $order->refresh();
        }

        $payment = $order->payment;
        if ($order->status !== OrderStatus::PendingPayment || $payment === null || $payment->expires_at->isPast()) {
            $message = $order->status === OrderStatus::PendingPayment || $order->status === OrderStatus::Expired
                ? 'Pesanan ini sudah tidak bisa dibayar karena batas waktu pembayaran habis.'
                : 'Pesanan ini sudah tidak bisa dibayar.';

            throw new ApiException($message, 'ORDER_NOT_PAYABLE', 409, extra: ['order_status' => $order->status->value]);
        }

        return response()->json(['data' => [
            'order_number' => $order->order_number,
            'status' => $payment->status->value,
            'checkout_url' => $payment->checkout_url,
            'amount' => $payment->amount,
            'expires_at' => $payment->expires_at->toIso8601String(),
            'seconds_left' => (int) now()->diffInSeconds($payment->expires_at),
        ]]);
    }

    /**
     * ORD-05 · Batalkan pesanan yang belum dibayar
     */
    public function cancel(Request $request, string $orderNumber, OrderCancellationService $cancellation): JsonResponse
    {
        $order = $this->find($request, $orderNumber);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        return (new OrderDetailResource($cancellation->cancel($order, $reason)))->response();
    }

    private function find(Request $request, string $orderNumber): Order
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if ($order === null || $request->user()->cannot('view', $order)) {
            throw new ApiException('Pesanan tidak ditemukan.', 'ORDER_NOT_FOUND', 404);
        }

        return $order;
    }
}
