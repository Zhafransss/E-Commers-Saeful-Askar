<?php

namespace App\Services\Gapura;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * A verified Finish Notify (WH-01), reduced to what the order flow needs.
 */
final readonly class GapuraNotification
{
    public function __construct(
        public string $partnerReferenceNo,
        public string $referenceNo,
        public string $statusCode,
        public ?PaymentStatus $status,
        public ?int $amount,
        public ?CarbonImmutable $paidAt,
        public ?string $method,
        public ?string $channel,
        public ?string $closedReason,
    ) {}
}
