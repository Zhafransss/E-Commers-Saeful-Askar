<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/addresses-regions.md
 */
class AddressController extends Controller
{
    /**
     * ADDR-01 · Daftar alamat saya
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADDR-01');
    }

    /**
     * ADDR-02 · Tambah alamat
     */
    public function store(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADDR-02');
    }

    /**
     * ADDR-03 · Detail alamat
     */
    public function show(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADDR-03');
    }

    /**
     * ADDR-04 · Ubah alamat
     */
    public function update(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADDR-04');
    }

    /**
     * ADDR-05 · Hapus alamat
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADDR-05');
    }

    /**
     * ADDR-06 · Jadikan alamat utama
     */
    public function setDefault(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADDR-06');
    }
}
