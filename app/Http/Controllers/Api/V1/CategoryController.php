<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/catalog.md
 */
class CategoryController extends Controller
{
    /**
     * CAT-01 · Daftar kategori
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('CAT-01');
    }
}
