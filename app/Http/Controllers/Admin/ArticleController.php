<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md
 */
class ArticleController extends Controller
{
    /**
     * ADM-02 · Daftar artikel (admin)
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-02');
    }

    /**
     * ADM-03 · Buat artikel
     */
    public function store(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-03');
    }

    /**
     * ADM-04 · Detail artikel (admin)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADM-04');
    }

    /**
     * ADM-05 · Ubah artikel (POST + _method=PUT)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADM-05');
    }

    /**
     * ADM-06 · Hapus artikel
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADM-06');
    }

    /**
     * ADM-07 · Terbitkan / jadikan draft
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        throw new NotImplementedException('ADM-07');
    }

    /**
     * ADM-08 · Upload gambar untuk isi artikel
     */
    public function uploadImage(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-08');
    }
}
