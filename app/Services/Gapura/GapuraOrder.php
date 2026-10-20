<?php

namespace App\Services\Gapura;

use Carbon\CarbonImmutable;

/**
 * What Create Order (INT-GP-01) gives back: DANA's reference and the hosted checkout page, plus the deadline
 * that was actually sent (`validUpTo`), which the sandbox may have shortened.
 */
final readonly class GapuraOrder
{
    public function __construct(
        public string $referenceNo,
        public string $partnerReferenceNo,
        public string $checkoutUrl,
        public CarbonImmutable $expiresAt,
    ) {}
}
