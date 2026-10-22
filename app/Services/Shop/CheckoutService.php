<?php

namespace App\Services\Shop;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Exceptions\IntegrationException;
use App\Http\Resources\Api\V1\CartResource;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Jobs\Jubelio\UpdateJubelioContactJob;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use App\Services\Jubelio\ContactService;
use App\Services\Jubelio\SalesOrderService;
use App\Services\Jubelio\StockSyncService;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ORD-01 (docs/api/checkout-orders.md), in the contract's order: validate → rate → price → live stock
 * → Jubelio contact → order → Jubelio sales order → Gapura order → remove the bought lines from the cart.
 *
 * Nothing here may happen twice. One checkout per customer runs at a time (a second click while the
 * first is still working gets `DUPLICATE_REQUEST`), and the answer to an `Idempotency-Key` is kept for
 * 24 hours, so sending the same request again returns the same order instead of making another.
 *
 * When Jubelio or Gapura fails midway the order is cancelled and marked as a failed checkout, the
 * sales order (if any) is given back, and the cart is left alone so the customer can simply try again.
 */
class CheckoutService
{
    private const RESPONSE_TTL_HOURS = 24;

    private const LOCK_SECONDS = 120;

    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingRateService $rates,
        private readonly StockSyncService $stock,
        private readonly ContactService $contacts,
        private readonly SalesOrderService $salesOrders,
        private readonly GapuraClient $gapura,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderStatusRecorder $status,
    ) {}

    /**
     * @param  Closure(Order): array<string, mixed>  $respond  Builds the response data of a placed order; the
     *                                                         result is kept for the idempotency key
     * @param  list<int>|null  $itemIds  The cart lines being bought; null means the whole cart
     * @return array<string, mixed> The response data (a replay returns the stored one)
     */
    public function handle(User $user, string $idempotencyKey, int $addressId, string $rateId, ?string $note, int $expectedTotal, Closure $respond, ?array $itemIds = null): array
    {
        $storedKey = "checkout:response:{$user->id}:{$idempotencyKey}";

        if (is_array($stored = Cache::get($storedKey))) {
            return $stored;
        }

        $lock = Cache::lock("checkout:user:{$user->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new ApiException('Pesanan Anda sedang diproses. Tunggu sebentar, jangan tekan Bayar lagi.', 'DUPLICATE_REQUEST', 409);
        }

        try {
            // The first request may have finished between the check above and getting the lock.
            if (is_array($stored = Cache::get($storedKey))) {
                return $stored;
            }

            $response = $respond($this->place($user, $addressId, $rateId, $note, $expectedTotal, $itemIds));
            Cache::put($storedKey, $response, now()->addHours(self::RESPONSE_TTL_HOURS));

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>|null  $itemIds
     */
    private function place(User $user, int $addressId, string $rateId, ?string $note, int $expectedTotal, ?array $itemIds): Order
    {
        $address = $user->addresses()->find($addressId)
            ?? throw new ApiException('Alamat tidak ditemukan.', 'ADDRESS_NOT_FOUND', 404);

        $lines = $this->cart->items($user)
            ->when($itemIds !== null, fn ($items) => $items->whereIn('id', $itemIds))
            ->values();
        $this->assertCartReady($lines);
        $this->ensureProfilePhone($user, $address);

        $rate = $this->rates->resolve($rateId, $user, $address, $itemIds);

        $subtotal = (int) $lines->sum(fn (CartItem $line) => $line->variant->price * $line->qty);
        $amounts = [
            'subtotal' => $subtotal,
            'shipping_cost' => $rate['cost'],
            'insurance_cost' => $rate['insurance_cost'],
            'discount' => 0,
            'tax' => 0,
            'grand_total' => $subtotal + $rate['cost'] + $rate['insurance_cost'],
        ];

        if ($amounts['grand_total'] !== $expectedTotal) {
            $formatted = 'Rp'.number_format($amounts['grand_total'], 0, ',', '.');

            throw new ApiException("Harga atau ongkir berubah. Total baru {$formatted}.", 'PRICE_CHANGED', 409, extra: ['amounts' => $amounts]);
        }

        $this->assertStockInJubelio($lines);

        // Before the order exists: a Jubelio failure here must leave nothing behind.
        $this->contacts->ensure($user, $address);

        $order = $this->createOrder($user, $address, $lines, $rate, $amounts, $note);

        $this->createSalesOrder($order);
        $this->createGapuraOrder($order, $lines);

        return $order->load(['items', 'payment', 'statusHistories']);
    }

    /**
     * @param  Collection<int, CartItem>  $lines
     */
    private function assertCartReady(Collection $lines): void
    {
        if ($lines->isEmpty()) {
            throw new ApiException('Keranjang Anda kosong.', 'CART_EMPTY', 422);
        }

        if ($lines->contains(fn (CartItem $line) => $line->issue() !== null)) {
            throw new ApiException(
                'Ada item di keranjang yang perlu diperbaiki sebelum checkout.',
                'CART_HAS_ISSUES',
                409,
                extra: ['cart' => (new CartResource($lines))->response()->getData(true)['data']],
            );
        }
    }

    /**
     * The courier and Jubelio need a phone number on the account. A customer who never had one on the profile
     * (there is no page to add it) uses the number of the delivery address, which is always valid; it is kept
     * on the profile so the next order does not ask again.
     */
    private function ensureProfilePhone(User $user, Address $address): void
    {
        if (filled($user->phone)) {
            return;
        }

        if (blank($address->phone)) {
            throw new ApiException('Lengkapi nomor HP di alamat pengiriman Anda sebelum checkout.', 'PROFILE_INCOMPLETE', 422);
        }

        // A number another account already has stays with that account; the order still goes through, since
        // the courier is given the address's own number.
        if (PhoneNumber::isTaken($address->phone, $user->id)) {
            return;
        }

        $user->forceFill(['phone' => $address->phone])->save();

        if ($user->jubelio_contact_id !== null) {
            UpdateJubelioContactJob::dispatch($user->id);
        }
    }

    /**
     * Stock is read from Jubelio right now, not from the local copy, because Shopee sells the same units
     * (ORD-01 step 4). The local copy is refreshed on the way.
     *
     * @param  Collection<int, CartItem>  $lines
     */
    private function assertStockInJubelio(Collection $lines): void
    {
        $this->stock->syncVariants($lines->map(fn (CartItem $line) => $line->variant));

        $short = $lines
            ->filter(fn (CartItem $line) => $line->qty > $line->variant->sellableQty())
            ->map(fn (CartItem $line) => [
                'variant_id' => $line->variant->id,
                'sku' => $line->variant->sku,
                'name' => $line->variant->name,
                'requested_qty' => $line->qty,
                'available_qty' => $line->variant->sellableQty(),
            ])
            ->values();

        if ($short->isNotEmpty()) {
            throw new ApiException('Stok beberapa produk sudah berubah. Periksa kembali keranjang Anda.', 'OUT_OF_STOCK', 409, extra: ['items' => $short->all()]);
        }
    }

    /**
     * The order and everything copied into it, in one transaction.
     *
     * @param  Collection<int, CartItem>  $lines
     * @param  array<string, mixed>  $rate
     * @param  array<string, int>  $amounts
     */
    private function createOrder(User $user, Address $address, Collection $lines, array $rate, array $amounts, ?string $note): Order
    {
        return DB::transaction(function () use ($user, $address, $lines, $rate, $amounts, $note): Order {
            $order = Order::create([
                'user_id' => $user->id,
                'address_id' => $address->id,
                'status' => OrderStatus::PendingPayment,
                'shipping_recipient_name' => $address->recipient_name,
                'shipping_phone' => $address->phone,
                'shipping_address_line' => $address->address_line,
                'shipping_province_id' => $address->province_id,
                'shipping_province' => $address->province_name,
                'shipping_city_id' => $address->city_id,
                'shipping_city' => $address->city_name,
                'shipping_district_id' => $address->district_id,
                'shipping_district' => $address->district_name,
                'shipping_subdistrict_id' => $address->subdistrict_id,
                'shipping_subdistrict' => $address->subdistrict_name,
                'shipping_post_code' => $address->post_code,
                'courier_code' => $rate['courier_code'],
                'courier_name' => $rate['courier_name'],
                'service_code' => $rate['service_code'],
                'service_name' => $rate['service_name'],
                'service_type' => $rate['service_type'],
                'etd_text' => $rate['etd_text'],
                'note' => filled($note) ? trim($note) : null,
                'payment_expires_at' => now()->addSeconds((int) config('gapura.payment_duration')),
                'jubelio_sync_status' => JubelioSyncStatus::Pending,
            ] + $amounts);

            $this->numbers->assign($order);

            foreach ($lines as $line) {
                $variant = $line->variant;
                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'jubelio_item_id' => $variant->jubelio_item_id,
                    'sku' => $variant->sku,
                    'product_name' => $variant->product->name,
                    'variant_name' => collect($variant->variation_values)->pluck('value')->filter()->implode(', ') ?: null,
                    'image_url' => $variant->image_url,
                    'unit' => $variant->product->sell_unit ?: 'Unit',
                    'price' => $variant->price,
                    'qty' => $line->qty,
                    'weight_gram' => $variant->effectiveWeight(),
                    'subtotal' => $variant->price * $line->qty,
                ]);
            }

            $this->status->start($order, OrderStatusSource::System);

            return $order;
        });
    }

    /**
     * ORD-01 step 7. A failure ends the checkout: the order is cancelled, the cart stays.
     */
    private function createSalesOrder(Order $order): void
    {
        try {
            $salesOrder = $this->salesOrders->create($order);
        } catch (Throwable $e) {
            $this->failCheckout($order, 'Gagal membuat pesanan di Jubelio', $e);

            throw $this->asBusy($e);
        }

        $order->forceFill([
            'jubelio_salesorder_id' => $salesOrder['id'],
            'jubelio_salesorder_no' => $salesOrder['no'],
            'jubelio_sync_status' => JubelioSyncStatus::Synced,
        ])->save();
    }

    /**
     * ORD-01 step 8. A failure gives the sales order back, then ends the checkout like step 7.
     */
    /**
     * @param  Collection<int, CartItem>  $lines  The cart lines this order was made from
     */
    private function createGapuraOrder(Order $order, Collection $lines): void
    {
        try {
            $gapura = $this->gapura->withinCustomerRequest()->createOrder(
                $order->order_number,
                $order->grand_total,
                now()->addSeconds((int) config('gapura.payment_duration')),
                "Pesanan {$order->order_number}",
                route('checkout.finish', ['order' => $order->order_number]),
            );
        } catch (Throwable $e) {
            $this->releaseSalesOrder($order);
            $this->failCheckout($order, 'Gagal membuat pembayaran di Gapura', $e);

            throw $this->asBusy($e);
        }

        DB::transaction(function () use ($order, $lines, $gapura): void {
            Payment::create([
                'order_id' => $order->id,
                'gapura_reference_no' => $gapura->referenceNo,
                'partner_reference_no' => $order->order_number,
                'checkout_url' => $gapura->checkoutUrl,
                'amount' => $order->grand_total,
                'status' => PaymentStatus::Pending,
                'expires_at' => $gapura->expiresAt,
            ]);
            $order->forceFill(['payment_expires_at' => $gapura->expiresAt])->save();
            // Only what was bought; lines the customer left unticked stay in the cart.
            CartItem::whereIn('id', $lines->modelKeys())->delete();
        });
    }

    /**
     * Cancels the sales order that a failed checkout leaves behind. If Jubelio is not reachable now, the
     * queue keeps trying and the order is marked failed until it succeeds.
     */
    private function releaseSalesOrder(Order $order): void
    {
        try {
            $this->salesOrders->cancel($order);
        } catch (Throwable $e) {
            $order->forceFill(['jubelio_sync_status' => JubelioSyncStatus::Failed])->save();
            CancelSalesOrderJob::dispatch($order->id);
        }
    }

    private function failCheckout(Order $order, string $reason, Throwable $e): void
    {
        $this->status->transition($order, OrderStatus::Cancelled, OrderStatusSource::System, $reason, [
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'is_checkout_failed' => true,
            'jubelio_last_error' => $e instanceof IntegrationException ? $e->detail() : $e->getMessage(),
        ]);
    }

    /**
     * What the customer reads when Jubelio or Gapura failed: no technical detail, and no payment was made.
     */
    private function asBusy(Throwable $e): Throwable
    {
        return $e instanceof IntegrationException
            ? $e->withMessage('Pesanan belum bisa dibuat karena sistem toko sedang sibuk. Tidak ada tagihan yang dibuat. Silakan coba lagi.')
            : $e;
    }
}
