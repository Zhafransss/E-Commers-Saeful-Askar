<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cart rules from docs/api/cart.md. Writes run in a transaction that locks the
 * user's row, so two quick "add to cart" clicks cannot both pass the stock
 * and max_qty checks on stale numbers.
 *
 * Quantity rules for CART-02 and CART-03:
 *   1. qty ≥ 1 (request validation);
 *   2. qty ≤ the variant's available stock;
 *   3. qty ≥ the product's min_qty, when set;
 *   4. total qty of all variants of the product in the cart ≤ max_qty, when set.
 */
class CartService
{
    /**
     * Lines with their variant and product, last added first (CART-01).
     *
     * @return Collection<int, CartItem>
     */
    public function items(User $user): Collection
    {
        return $user->cartItems()->with('variant.product')->orderByDesc('id')->get();
    }

    /**
     * CART-02. Adding a variant already in the cart raises its qty.
     *
     * @return bool Whether a new line was created
     */
    public function add(User $user, int $variantId, int $qty): bool
    {
        $variant = ProductVariant::with('product')->find($variantId)
            ?? throw new ApiException('Varian produk tidak ditemukan.', 'VARIANT_NOT_FOUND', 404);

        $this->assertSellable($variant);

        return DB::transaction(function () use ($user, $variant, $qty): bool {
            $this->lock($user);

            $item = $user->cartItems()->where('product_variant_id', $variant->id)->first();
            $total = ($item?->qty ?? 0) + $qty;
            $this->assertQty($user, $variant, $total, $item?->id);

            if ($item !== null) {
                $item->update(['qty' => $total]);

                return false;
            }

            $user->cartItems()->create(['product_variant_id' => $variant->id, 'qty' => $total]);

            return true;
        });
    }

    /**
     * CART-03. Replaces the qty of one line.
     */
    public function update(User $user, int $itemId, int $qty): void
    {
        $item = $this->find($user, $itemId);
        $this->assertSellable($item->variant);

        DB::transaction(function () use ($user, $item, $qty): void {
            $this->lock($user);
            $this->assertQty($user, $item->variant, $qty, $item->id);
            $item->update(['qty' => $qty]);
        });
    }

    /**
     * CART-04. Inactive lines can always be removed.
     */
    public function remove(User $user, int $itemId): void
    {
        $this->find($user, $itemId)->delete();
    }

    /**
     * CART-05.
     */
    public function clear(User $user): void
    {
        $user->cartItems()->delete();
    }

    /**
     * CART-06 in one aggregate query, without loading products. Guests get 0.
     *
     * @return array{total_qty: int, has_issue: bool}
     */
    public function count(?User $user): array
    {
        if ($user === null) {
            return ['total_qty' => 0, 'has_issue' => false];
        }

        // Same conditions as CartItem::issue(): inactive, or more than the (non-negative) stock.
        $row = CartItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'cart_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('cart_items.user_id', $user->id)
            ->selectRaw('COALESCE(SUM(cart_items.qty), 0) AS total_qty')
            ->selectRaw('COALESCE(MAX(product_variants.is_active = 0 OR products.is_active = 0
                OR cart_items.qty > GREATEST(product_variants.available_qty, 0)), 0) AS has_issue')
            ->toBase()
            ->first();

        return ['total_qty' => (int) $row->total_qty, 'has_issue' => (bool) $row->has_issue];
    }

    /**
     * A line of the user's cart; another user's line is "not found" (404, not
     * 403), so ids do not leak.
     */
    private function find(User $user, int $itemId): CartItem
    {
        return $user->cartItems()->with('variant.product')->find($itemId)
            ?? throw new ApiException('Item keranjang tidak ditemukan.', 'CART_ITEM_NOT_FOUND', 404);
    }

    private function assertSellable(ProductVariant $variant): void
    {
        if (! $variant->is_active || ! $variant->product->is_active) {
            throw new ApiException('Produk ini sudah tidak dijual.', 'VARIANT_INACTIVE', 422);
        }
    }

    /**
     * Rules 2–4 for the qty the line would have after the change.
     */
    private function assertQty(User $user, ProductVariant $variant, int $qty, ?int $exceptItemId): void
    {
        $available = $variant->sellableQty();
        if ($qty > $available) {
            throw new ApiException(
                $available === 0 ? 'Stok produk ini habis.' : "Stok tidak cukup. Tersisa {$available} unit.",
                'OUT_OF_STOCK',
                409,
                ['qty' => [$available === 0 ? 'Stok habis.' : "Maksimal {$available} unit untuk varian ini."]],
                ['available_qty' => $available],
            );
        }

        $product = $variant->product;
        if ($product->min_qty !== null && $qty < $product->min_qty) {
            throw new ApiException(
                "Minimal pembelian produk ini {$product->min_qty} unit.",
                'QTY_LIMIT_EXCEEDED',
                422,
                ['qty' => ["Minimal {$product->min_qty} unit."]],
                ['min_qty' => $product->min_qty],
            );
        }

        if ($product->max_qty !== null) {
            $otherVariants = $user->cartItems()
                ->when($exceptItemId !== null, fn ($query) => $query->whereKeyNot($exceptItemId))
                ->whereHas('variant', fn ($query) => $query->where('product_id', $product->id))
                ->sum('qty');

            if ($otherVariants + $qty > $product->max_qty) {
                throw new ApiException(
                    "Maksimal pembelian produk ini {$product->max_qty} unit per pesanan.",
                    'QTY_LIMIT_EXCEEDED',
                    422,
                    ['qty' => ["Maksimal {$product->max_qty} unit."]],
                    ['max_qty' => $product->max_qty],
                );
            }
        }
    }

    private function lock(User $user): void
    {
        User::whereKey($user->id)->lockForUpdate()->first();
    }
}
