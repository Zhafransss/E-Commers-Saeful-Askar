<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md
 */
class SyncController extends Controller
{
    /**
     * ADM-13 · Jalankan sinkron manual
     */
    public function store(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-13');
    }

    /**
     * ADM-14 · Riwayat sinkron
     */
    public function logs(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-14');
    }
}
