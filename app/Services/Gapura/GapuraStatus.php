<?php

namespace App\Services\Gapura;

use App\Enums\PaymentStatus;

/**
 * DANA's `latestTransactionStatus` (Query Payment and Finish Notify) as our PaymentStatus.
 *
 *   00 paid and final         → Paid
 *   02 paying, payment is made → Paid (DANA: "mark payment as Success")
 *   01 created, not yet paid  → Pending
 *   05 closed (expired or cancelled) → Expired
 *   07 order not found        → Expired (DANA: "mark payment as Failed")
 */
final class GapuraStatus
{
    public static function toPaymentStatus(string $code): ?PaymentStatus
    {
        return match ($code) {
            '00', '02' => PaymentStatus::Paid,
            '01' => PaymentStatus::Pending,
            '05', '07' => PaymentStatus::Expired,
            default => null,
        };
    }
}
