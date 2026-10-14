<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
