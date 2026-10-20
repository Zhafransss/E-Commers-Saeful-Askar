<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never reach Jubelio, Jubelio Shipment, or Gapura: every call must
        // be answered by Http::fake() (or, for the DANA SDK, a Guzzle mock handler) with a fixture
        // from tests/Fixtures (INT-HDL).
        Http::preventStrayRequests();

        // Keep fake integration calls out of storage/logs/integrations-*.log.
        // Tests that inspect the log point this channel at Monolog's TestHandler.
        config(['logging.channels.integrations' => ['driver' => 'monolog', 'handler' => NullHandler::class]]);
    }
}
