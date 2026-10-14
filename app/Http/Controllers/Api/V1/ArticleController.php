<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/articles.md
 */
class ArticleController extends Controller
{
    /**
     * ART-01 · Daftar artikel
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('ART-01');
    }

    /**
     * ART-02 · Detail artikel
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        throw new NotImplementedException('ART-02');
    }
}
