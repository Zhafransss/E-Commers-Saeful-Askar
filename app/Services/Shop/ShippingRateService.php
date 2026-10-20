<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Shipping\RateProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SHIP-01 (docs/api/shipping.md). The package is read from the customer's cart on the server, never
 * from the request. Each rate handed out gets a `rate_id` that is remembered for 30 minutes together
 * with who it was made for (user, address, cart contents), so checkout (ORD-01) can trust the cost
 * and nobody can invent one.
 */
class ShippingRateService
{
    public function __construct(
        private readonly RateProvider $shipment,
        private readonly CartService $cart,
    ) {}

    /**
     * @param  list<string>  $serviceTypes  Empty means every type
     * @return array<string, mixed> The SHIP-01 `data` object
     */
    public function quote(User $user, Address $address, array $serviceTypes = []): array
    {
        $package = $this->package($user);

        if ($package['items']->isEmpty()) {
            throw new ApiException('Keranjang Anda kosong.', 'CART_EMPTY', 422);
        }

        $raw = $this->rawRates($address, $package['weight']);

        if ($serviceTypes !== []) {
            $raw = array_values(array_filter($raw, fn (array $rate) => in_array($rate['service_type'], $serviceTypes, true)));
        }

        if ($raw === []) {
            $kg = round($package['weight'] / 1000, 1);

            throw new ApiException(
                "Belum ada kurir yang bisa mengirim ke alamat ini untuk berat {$kg} kg. Silakan hubungi toko.",
                'NO_SHIPPING_SERVICE',
                422,
            );
        }

        usort($raw, fn (array $a, array $b) => $a['cost'] <=> $b['cost']);
        $expiresAt = now()->addSeconds((int) config('shipping.rate_ttl'));
        $origin = config('shipping.origin');

        $rates = array_map(function (array $rate) use ($user, $address, $package, $expiresAt): array {
            $rate = ['rate_id' => 'rt_'.Str::lower(Str::random(8))] + $rate;

            Cache::put($this->cacheKey($rate['rate_id']), $rate + [
                'user_id' => $user->id,
                'address_id' => $address->id,
                'signature' => $package['signature'],
                'weight' => $package['weight'],
            ], $expiresAt);

            return $rate;
        }, $raw);

        return [
            'origin' => [
                'name' => $origin['name'],
                'district' => $origin['district'],
                'city' => $origin['city'],
                'post_code' => $origin['post_code'],
            ],
            'destination' => [
                'address_id' => $address->id,
                'district' => $address->district_name,
                'city' => $address->city_name,
                'province' => $address->province_name,
                'post_code' => $address->post_code,
            ],
            'package' => [
                'total_weight' => $package['weight'],
                'item_count' => $package['items']->count(),
                'subtotal' => $package['subtotal'],
            ],
            'rates' => $rates,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * The rate a checkout names, if it was made for this user, address, and cart contents and has not
     * expired (ORD-01 step 2). Anything else is `RATE_EXPIRED`.
     *
     * @return array<string, mixed> The rate fields, without the bookkeeping keys
     */
    public function resolve(string $rateId, User $user, Address $address): array
    {
        $stored = Cache::get($this->cacheKey($rateId));
        $package = $this->package($user);

        if (! is_array($stored)
            || $stored['user_id'] !== $user->id
            || $stored['address_id'] !== $address->id
            || $stored['signature'] !== $package['signature']) {
            throw new ApiException('Ongkir sudah kedaluwarsa atau berubah. Pilih kurir lagi.', 'RATE_EXPIRED', 409);
        }

        return array_diff_key($stored, array_flip(['user_id', 'address_id', 'signature', 'weight']));
    }

    /**
     * The payable part of the cart: lines whose product was deactivated do not count (CART-01, SHIP-01).
     *
     * @return array{items: Collection<int, CartItem>, weight: int, subtotal: int, signature: string}
     */
    public function package(User $user): array
    {
        $items = $this->cart->items($user)->reject(fn (CartItem $item) => $item->issue() === CartItem::ISSUE_PRODUCT_INACTIVE)->values();

        return [
            'items' => $items,
            'weight' => (int) $items->sum(fn (CartItem $item) => $item->variant->effectiveWeight() * $item->qty),
            'subtotal' => (int) $items->sum(fn (CartItem $item) => $item->variant->price * $item->qty),
            'signature' => $items->sortBy('product_variant_id')->map(fn (CartItem $item) => "{$item->product_variant_id}:{$item->qty}")->implode('|'),
        ];
    }

    /**
     * The same address and weight within 5 minutes reuse the provider's answer (SHIP-01).
     *
     * @return list<array<string, mixed>>
     */
    private function rawRates(Address $address, int $weight): array
    {
        return Cache::remember(
            "shipping:quote:{$address->subdistrict_id}:{$weight}",
            (int) config('shipping.quote_cache_ttl'),
            fn () => $this->shipment->rates($address, $weight),
        );
    }

    private function cacheKey(string $rateId): string
    {
        return "shipping:rate:{$rateId}";
    }
}
