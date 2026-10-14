<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/catalog.md
 */
class ProductController extends Controller
{
    /**
     * CAT-02 · Daftar dan pencarian produk
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('CAT-02');
    }

    /**
     * CAT-03 · Detail produk
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        throw new NotImplementedException('CAT-03');
    }
}
