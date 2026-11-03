<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ShippingVoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A voucher that lowers only the shipping cost of an order (revised scope 3 Nov 2026). One voucher per order.
 *
 * Use is counted from the orders themselves: an order that expired or was cancelled (including a failed
 * checkout) gives its use back, so no counter can drift from what really happened.
 */
class ShippingVoucher extends Model
{
    use HasFactory;

    /** Orders in these statuses do not use up the voucher. */
    public const RELEASED = [OrderStatus::Expired, OrderStatus::Cancelled];

    protected $fillable = ['name', 'type', 'amount', 'min_subtotal', 'starts_at', 'ends_at', 'quota', 'per_user_limit'];

    protected function casts(): array
    {
        return [
            'type' => ShippingVoucherType::class,
            'amount' => 'integer',
            'min_subtotal' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'quota' => 'integer',
            'per_user_limit' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Started and not ended yet.
     *
     * @param  Builder<ShippingVoucher>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isRunning(): bool
    {
        return $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * What it takes off this shipping cost; never more than the cost itself.
     */
    public function discountFor(int $shippingCost): int
    {
        $discount = $this->type === ShippingVoucherType::FreeShipping ? $shippingCost : (int) $this->amount;

        return max(0, min($discount, $shippingCost));
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function activeOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', array_map(fn (OrderStatus $s) => $s->value, self::RELEASED));
    }

    public function usedCount(): int
    {
        return $this->activeOrders()->count();
    }

    public function usedBy(User $user): int
    {
        return $this->activeOrders()->where('user_id', $user->id)->count();
    }

    /** Uses left for everyone; null = no limit. */
    public function remainingQuota(?int $used = null): ?int
    {
        return $this->quota === null ? null : max(0, $this->quota - ($used ?? $this->usedCount()));
    }

    /** Uses left for this customer; null = no limit. */
    public function remainingFor(User $user): ?int
    {
        return $this->per_user_limit === null ? null : max(0, $this->per_user_limit - $this->usedBy($user));
    }
}
