<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/cart.md
 */
class CartController extends Controller
{
    /**
     * CART-01 · Lihat keranjang
     */
    public function show(Request $request): JsonResponse
    {
        throw new NotImplementedException('CART-01');
    }

    /**
     * CART-02 · Tambah item ke keranjang
     */
    public function storeItem(Request $request): JsonResponse
    {
        throw new NotImplementedException('CART-02');
    }

    /**
     * CART-03 · Ubah jumlah item
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('CART-03');
    }

    /**
     * CART-04 · Hapus item
     */
    public function destroyItem(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('CART-04');
    }

    /**
     * CART-05 · Kosongkan keranjang
     */
    public function clear(Request $request): JsonResponse
    {
        throw new NotImplementedException('CART-05');
    }

    /**
     * CART-06 · Jumlah item untuk badge header
     */
    public function count(Request $request): JsonResponse
    {
        throw new NotImplementedException('CART-06');
    }
}
