<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base for every API resource. Extend this instead of JsonResource so that
 * `SomeResource::collection($paginator)` produces the contract list shape:
 * { "data": [...], "meta": { current_page, per_page, total, last_page } }.
 *
 * Dates need no formatting here: AppServiceProvider serializes every date as
 * ISO 8601 +07:00.
 */
abstract class ApiResource extends JsonResource
{
    protected static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
