<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\User;
use App\Services\Shop\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contract: docs/api/cart.md. Every write answers the whole Cart, so the
 * frontend replaces its cart state with the latest response.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * CART-01 · Lihat keranjang
     */
    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user());
    }

    /**
     * CART-02 · Tambah item ke keranjang
     */
    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $created = $this->cart->add($request->user(), (int) $request->validated('variant_id'), (int) $request->validated('qty'));

        return $this->respond($request->user(), 'Produk ditambahkan ke keranjang.', $created ? 201 : 200);
    }

    /**
     * CART-03 · Ubah jumlah item
     */
    public function updateItem(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $this->cart->update($request->user(), $id, (int) $request->validated('qty'));

        return $this->respond($request->user());
    }

    /**
     * CART-04 · Hapus item
     */
    public function destroyItem(Request $request, int $id): JsonResponse
    {
        $this->cart->remove($request->user(), $id);

        return $this->respond($request->user(), 'Produk dihapus dari keranjang.');
    }

    /**
     * CART-05 · Kosongkan keranjang
     */
    public function clear(Request $request): JsonResponse
    {
        $this->cart->clear($request->user());

        return $this->respond($request->user(), 'Keranjang dikosongkan.');
    }

    /**
     * CART-06 · Jumlah item untuk badge header. Public: guests get 0, never 401.
     */
    public function count(): JsonResponse
    {
        return response()->json(['data' => $this->cart->count(Auth::guard('sanctum')->user())]);
    }

    private function respond(User $user, ?string $message = null, int $status = 200): JsonResponse
    {
        $body = ['data' => (new CartResource($this->cart->items($user)))->resolve()];

        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }
}
