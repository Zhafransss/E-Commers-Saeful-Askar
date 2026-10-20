<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminOrderDetailResource;
use App\Http\Resources\Admin\AdminOrderSummaryResource;
use App\Jobs\Jubelio\PushOrderToJubelioJob;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Admin\JubelioResend;
use App\Services\Shop\OrderPaymentService;
use App\Services\Shop\PaymentOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contract: docs/api/admin.md
 */
class OrderController extends Controller
{
    /**
     * ADM-09 · Daftar pesanan website (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'jubelio_sync_status' => ['nullable', Rule::enum(JubelioSyncStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $statuses = collect(explode(',', (string) ($filters['status'] ?? '')))->map(fn ($s) => trim($s))->filter()->values();
        if ($unknown = $statuses->first(fn ($s) => OrderStatus::tryFrom($s) === null)) {
            throw ValidationException::withMessages(['status' => ["Status tidak dikenal: {$unknown}."]]);
        }

        $orders = Order::query()
            ->with(['user', 'payment'])
            ->withCount('items')
            ->when($statuses->isNotEmpty(), fn ($q) => $q->whereIn('status', $statuses->all()))
            ->when($filters['jubelio_sync_status'] ?? null, fn ($q, $v) => $q->where('jubelio_sync_status', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', "{$v} 00:00:00"))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', "{$v} 23:59:59"))
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q
                    ->where('order_number', 'like', $like)
                    ->orWhere('tracking_no', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->latest('created_at')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminOrderSummaryResource::collection($orders)->response();
    }

    /**
     * ADM-10 · Detail pesanan (admin)
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        return (new AdminOrderDetailResource($this->find($orderNumber)->load(['user', 'items', 'payment', 'statusHistories'])))->response();
    }

    /**
     * ADM-11 · Kirim ulang pesanan ke Jubelio
     */
    public function pushJubelio(Request $request, string $orderNumber, JubelioResend $resend): JsonResponse
    {
        $order = $this->find($orderNumber);
        $requested = $request->validate(['action' => ['nullable', Rule::in(['create', 'mark_paid', 'cancel'])]])['action'] ?? null;

        $action = $resend->resolve($order, $requested);
        $log = SyncLog::start(
            ['create' => SyncType::OrderPush, 'mark_paid' => SyncType::OrderPaid, 'cancel' => SyncType::OrderCancel][$action],
            SyncTrigger::Manual,
            $order->id,
        );
        $order->forceFill(['jubelio_sync_status' => JubelioSyncStatus::Pending])->save();
        PushOrderToJubelioJob::dispatch($order->id, $action, $log->id);

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'action' => $action,
                'sync_log_id' => $log->id,
                'jubelio_sync_status' => 'pending',
            ],
            'message' => 'Pesanan dikirim ulang ke Jubelio. Periksa status beberapa saat lagi.',
        ], 202);
    }

    /**
     * ADM-12 · Periksa ulang status pembayaran
     */
    public function refreshPayment(Request $request, string $orderNumber, OrderPaymentService $payments): JsonResponse
    {
        $order = $this->find($orderNumber)->load(['payment', 'user']);

        if ($order->payment === null) {
            throw new ApiException('Pesanan ini tidak punya order pembayaran di Gapura.', 'ORDER_HAS_NO_PAYMENT', 409);
        }

        $before = $order->status;
        $result = $payments->check($order);
        $order->refresh();
        $changed = $result['outcome'] === PaymentOutcome::Applied;

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'gapura_status' => $result['code'],
                'order_status_before' => $before->value,
                'order_status_after' => $order->status->value,
                'changed' => $changed,
            ],
            'message' => match (true) {
                $changed && $order->status === OrderStatus::Paid => 'Pembayaran ditemukan. Pesanan ditandai dibayar.',
                $changed => 'Batas waktu pembayaran sudah habis. Pesanan ditandai kedaluwarsa.',
                $result['outcome'] === PaymentOutcome::TooLate => 'Pembayaran ditemukan, tetapi pesanan sudah dibatalkan. Perlu penanganan manual (refund).',
                $result['outcome'] === PaymentOutcome::AmountMismatch => 'Jumlah yang dibayar tidak sama dengan tagihan. Pesanan tidak diubah.',
                default => 'Status pembayaran belum berubah.',
            },
        ]);
    }

    private function find(string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)->first()
            ?? throw new ApiException('Pesanan tidak ditemukan.', 'ORDER_NOT_FOUND', 404);
    }
}
