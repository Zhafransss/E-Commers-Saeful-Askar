<?php

/*
| Handler per webhook event type (docs/api/webhooks.md). Each handler implements
| App\Services\Webhooks\WebhookHandler and is added in the phase that builds the
| endpoint (docs/roadmap-backend.md). An event without a handler is marked failed.
*/

return [

    'handlers' => [

        // event_type 'invoice' → WH-01 (Fase 5)
        'xendit' => [],

        // event_type = Jubelio action: 'update-product' → WH-02, 'update-price' → WH-03,
        // 'update-qty' → WH-04 (Fase 2); 'update-salesorder' → WH-05 (Fase 5)
        'jubelio' => [],

    ],

];
