<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminShipmentResource;
use App\Models\Order;
use App\Services\Admin\ShipmentSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contract: docs/api/admin.md. Tracking numbers are read from Jubelio, never typed here.
 */
class ShipmentController extends Controller
{
    /** Orders that were paid and not closed unpaid: the ones a warehouse ships. */
    private const SHIPPABLE = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    /**
     * ADM-21 · Daftar pesanan dan resi (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'resi' => ['nullable', Rule::in(['ada', 'belum'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->with('user:id,name,phone')
            ->whereIn('status', self::SHIPPABLE)
            ->when(($filters['resi'] ?? null) === 'ada', fn ($q) => $q->whereNotNull('tracking_no'))
            ->when(($filters['resi'] ?? null) === 'belum', fn ($q) => $q->whereNull('tracking_no'))
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q
                    ->where('order_number', 'like', $like)
                    ->orWhere('tracking_no', 'like', $like)
                    ->orWhere('shipping_recipient_name', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->latest('created_at')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminShipmentResource::collection($orders)->response();
    }

    /**
     * ADM-22 · Ambil resi terbaru dari Jubelio (admin)
     */
    public function refresh(ShipmentSync $shipments): JsonResponse
    {
        $result = $shipments->run();

        $message = match (true) {
            $result['checked'] === 0 => 'Tidak ada pesanan yang menunggu resi.',
            $result['updated'] > 0 => "{$result['updated']} resi baru ditemukan di Jubelio.",
            $result['failed'] === $result['checked'] => 'Jubelio tidak bisa dihubungi. Coba lagi beberapa saat.',
            default => 'Belum ada resi baru di Jubelio.',
        };

        if ($result['remaining'] > 0) {
            $message .= " Masih ada {$result['remaining']} pesanan lain yang belum dicek; tekan tombol lagi.";
        }

        return response()->json(['data' => $result, 'message' => $message]);
    }
}
