<?php

use App\Services\Webhooks\Handlers;

/*
| Handler per webhook event type (docs/api/webhooks.md). Each handler implements
| App\Services\Webhooks\WebhookHandler and is added in the phase that builds the
| endpoint (docs/roadmap-backend.md). An event without a handler is marked failed.
*/

return [

    'handlers' => [

        // event_type 'notify' → WH-01
        'gapura' => [
            'notify' => Handlers\GapuraPaymentNotified::class,
        ],

        // event_type = Jubelio action; 'update-salesorder' → WH-05 (Fase 5)
        'jubelio' => [
            'update-product' => Handlers\JubelioProductUpdated::class,  // WH-02
            'update-price' => Handlers\JubelioProductUpdated::class,    // WH-03
            'update-qty' => Handlers\JubelioStockUpdated::class,        // WH-04
        ],

    ],

];
