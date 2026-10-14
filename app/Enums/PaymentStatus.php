<?php

namespace App\Enums;

/**
 * Local invoice status. Xendit's SETTLED is stored as PAID (WH-01).
 */
enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Expired = 'EXPIRED';
}
