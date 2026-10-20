<?php

namespace App\Enums;

/**
 * Local payment status. DANA's `latestTransactionStatus` maps to it in GapuraStatus:
 * 00 and 02 are PAID, 01 is PENDING, 05 (closed) and 07 (not found) are EXPIRED.
 */
enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Expired = 'EXPIRED';
}
