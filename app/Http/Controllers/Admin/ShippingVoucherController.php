<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveShippingVoucherRequest;
use App\Http\Resources\Admin\AdminShippingVoucherResource;
use App\Models\ShippingVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md ADM-30 – ADM-33. Shipping vouchers shown on the checkout (SHIP-02).
 *
 * A change applies to orders placed afterwards only: an order keeps the voucher name and the discount it got.
 * A voucher that orders have used cannot be deleted; it is ended by setting its end time instead, so the
 * record of who used it stays.
 */
class ShippingVoucherController extends Controller
{
    /**
     * ADM-30 · Daftar voucher ongkir
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $vouchers = ShippingVoucher::query()
            ->withCount(['orders as used_count' => fn ($query) => $query->whereNotIn('status', array_map(fn (OrderStatus $s) => $s->value, ShippingVoucher::RELEASED))])
            ->when(trim((string) ($filters['q'] ?? '')), fn ($query, $term) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest('starts_at')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminShippingVoucherResource::collection($vouchers)->response();
    }

    /**
     * ADM-31 · Tambah voucher ongkir
     */
    public function store(SaveShippingVoucherRequest $request): JsonResponse
    {
        $voucher = ShippingVoucher::create($request->validated());

        return (new AdminShippingVoucherResource($voucher))->response()->setStatusCode(201);
    }

    /**
     * ADM-32 · Ubah voucher ongkir
     */
    public function update(SaveShippingVoucherRequest $request, int $id): JsonResponse
    {
        $voucher = $this->find($id);
        $voucher->update($request->validated());

        return (new AdminShippingVoucherResource($voucher))->response();
    }

    /**
     * ADM-33 · Hapus voucher ongkir
     */
    public function destroy(int $id): JsonResponse
    {
        $voucher = $this->find($id);

        if ($voucher->orders()->exists()) {
            throw new ApiException(
                'Voucher ini sudah dipakai di pesanan, jadi tidak bisa dihapus. Ubah waktu berakhirnya untuk menghentikannya.',
                'VOUCHER_IN_USE',
                409,
            );
        }

        $voucher->delete();

        return response()->json(['message' => 'Voucher dihapus.']);
    }

    private function find(int $id): ShippingVoucher
    {
        return ShippingVoucher::find($id) ?? throw new ApiException('Voucher tidak ditemukan.', 'VOUCHER_NOT_FOUND', 404);
    }
}
