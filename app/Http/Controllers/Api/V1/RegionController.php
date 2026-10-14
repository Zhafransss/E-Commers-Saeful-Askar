<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/addresses-regions.md
 */
class RegionController extends Controller
{
    /**
     * REG-01 · Daftar provinsi
     */
    public function provinces(Request $request): JsonResponse
    {
        throw new NotImplementedException('REG-01');
    }

    /**
     * REG-02 · Daftar kota/kabupaten
     */
    public function cities(Request $request): JsonResponse
    {
        throw new NotImplementedException('REG-02');
    }

    /**
     * REG-03 · Daftar kecamatan
     */
    public function districts(Request $request): JsonResponse
    {
        throw new NotImplementedException('REG-03');
    }

    /**
     * REG-04 · Daftar kelurahan/desa
     */
    public function subdistricts(Request $request): JsonResponse
    {
        throw new NotImplementedException('REG-04');
    }
}
