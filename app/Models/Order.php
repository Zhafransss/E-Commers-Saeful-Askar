<?php

namespace App\Models;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Website order. Address, courier, and item data are snapshots taken at checkout.
 */
class Order extends Model
{
    use HasFactory;

    /**
     * Written exclusively by backend services (CheckoutService, webhook jobs),
     * never from request input, so the whole row is assignable.
     */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'jubelio_sync_status' => JubelioSyncStatus::class,
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'insurance_cost' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'grand_total' => 'integer',
            'is_checkout_failed' => 'boolean',
            'requires_refund' => 'boolean',
            'payment_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'jubelio_last_modified' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** v1 creates exactly one invoice per order (ORD-04 never re-invoices). */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }
}
