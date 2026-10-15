<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Replaces Laravel's default pagination block (`links` + 8 meta fields) with the
 * four fields the contract and the frontend mocks use (docs/api/README.md).
 */
class ApiResourceCollection extends AnonymousResourceCollection
{
    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
                'last_page' => $paginated['last_page'],
            ],
        ];
    }
}
