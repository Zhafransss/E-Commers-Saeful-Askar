<?php

namespace App\Enums;

enum SyncType: string
{
    case Categories = 'categories';
    case Products = 'products';
    case Stock = 'stock';
    case OrderPush = 'order_push';
    case OrderPaid = 'order_paid';
    case OrderCancel = 'order_cancel';
}
