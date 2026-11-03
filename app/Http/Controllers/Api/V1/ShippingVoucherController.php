<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShippingVoucher;
use App\Services\Shop\ShippingVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/shipping.md
 */
class ShippingVoucherController extends Controller
{
    /**
     * SHIP-02 · Voucher ongkir yang bisa dipakai
     */
    public function index(Request $request, ShippingVoucherService $vouchers): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $vouchers->availableFor($user)->map(fn (ShippingVoucher $voucher) => [
                'id' => $voucher->id,
                'name' => $voucher->name,
                'type' => $voucher->type->value,
                'type_label' => $voucher->type->label(),
                'amount' => $voucher->amount,
                'min_subtotal' => $voucher->min_subtotal,
                'ends_at' => $voucher->ends_at,
                'remaining_quota' => $voucher->remainingQuota(),
            ])->all(),
        ]);
    }
}
