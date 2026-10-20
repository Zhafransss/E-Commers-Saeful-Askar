<?php

namespace App\Services\Gapura;

use RuntimeException;

/**
 * A Finish Notify whose X-SIGNATURE could not be verified with DANA's public key
 * (or that lacked the signature headers). It must never be processed.
 */
class GapuraSignatureException extends RuntimeException {}
