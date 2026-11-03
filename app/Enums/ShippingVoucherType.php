<?php

namespace App\Enums;

/**
 * What a shipping voucher takes off the shipping cost (docs/api/admin.md ADM-30).
 */
enum ShippingVoucherType: string
{
    /** The whole shipping cost. */
    case FreeShipping = 'free_shipping';

    /** At most the voucher's amount, never more than the shipping cost. */
    case ShippingDiscount = 'shipping_discount';

    public function label(): string
    {
        return match ($this) {
            self::FreeShipping => 'Gratis ongkir',
            self::ShippingDiscount => 'Potongan ongkir',
        };
    }
}
