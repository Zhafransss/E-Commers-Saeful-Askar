<?php

namespace App\Enums;

enum SyncTrigger: string
{
    case Schedule = 'schedule';
    case Webhook = 'webhook';
    case Manual = 'manual';
    case Checkout = 'checkout';
}
