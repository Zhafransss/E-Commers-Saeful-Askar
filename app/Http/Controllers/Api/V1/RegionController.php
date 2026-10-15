<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Jubelio\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/addresses-regions.md. Lists come from Jubelio through
 * RegionService (cached 30 days per parent); unknown parents give [].
 */
class RegionController extends Controller
{
    public function __construct(private readonly RegionService $regions) {}

    /**
     * REG-01 · Daftar provinsi
     */
    public function provinces(): JsonResponse
    {
        return response()->json(['data' => $this->regions->provinces()]);
    }

    /**
     * REG-02 · Daftar kota/kabupaten
     */
    public function cities(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->regions->cities($this->parent($request, 'province_id', 'Provinsi wajib dipilih.'))]);
    }

    /**
     * REG-03 · Daftar kecamatan
     */
    public function districts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->regions->districts($this->parent($request, 'city_id', 'Kota/kabupaten wajib dipilih.'))]);
    }

    /**
     * REG-04 · Daftar kelurahan/desa
     */
    public function subdistricts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->regions->subdistricts($this->parent($request, 'district_id', 'Kecamatan wajib dipilih.'))]);
    }

    private function parent(Request $request, string $field, string $requiredMessage): string
    {
        return (string) $request->validate(
            [$field => ['required', 'string', 'max:10']],
            ["{$field}.required" => $requiredMessage],
        )[$field];
    }
}
