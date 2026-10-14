<?php

namespace App\Enums;

enum ShippingServiceType: string
{
    case Instant = 'instant';
    case SameDay = 'same_day';
    case Regular = 'regular';
    case Cargo = 'cargo';
}
