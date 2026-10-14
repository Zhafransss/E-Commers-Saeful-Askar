<?php

namespace App\Enums;

/**
 * Whether the website order is reflected in Jubelio (orders.jubelio_sync_status).
 */
enum JubelioSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
