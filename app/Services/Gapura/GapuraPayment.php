<?php

namespace App\Services\Gapura;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Payment state from Query Payment (INT-GP-02).
 */
final readonly class GapuraPayment
{
    public function __construct(
        public PaymentStatus $status,
        public string $statusCode,
        public ?string $referenceNo,
        public ?int $amount,
        public ?CarbonImmutable $paidAt,
        public ?string $method,
        public ?string $channel,
    ) {}
}
