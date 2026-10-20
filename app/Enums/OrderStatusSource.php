<?php

namespace App\Enums;

/**
 * Who caused an order status change (order_status_histories.source).
 */
enum OrderStatusSource: string
{
    case System = 'system';
    case Customer = 'customer';
    case Admin = 'admin';
    case Gapura = 'gapura';
    case Jubelio = 'jubelio';
}
