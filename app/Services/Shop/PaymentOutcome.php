<?php

namespace App\Services\Shop;

/**
 * What applying a payment result did to an order (OrderPaymentService).
 */
enum PaymentOutcome: string
{
    /** The order moved (paid or expired). */
    case Applied = 'applied';

    /** The order was already past this point; a repeated or late-but-harmless result. */
    case Unchanged = 'unchanged';

    /** Paid for an order that was already expired or cancelled: needs a refund or manual handling. */
    case TooLate = 'too_late';

    /** The amount paid is not the amount billed: nothing is applied. */
    case AmountMismatch = 'amount_mismatch';
}
