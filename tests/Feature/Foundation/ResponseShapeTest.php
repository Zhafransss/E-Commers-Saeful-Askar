<?php

use App\Http\Resources\ApiResource;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

/*
| Success responses must match the shapes the frontend mocks use
| (docs/api/README.md "Format response sukses" and "Aturan data").
*/

beforeEach(function () {
    $resource = new class(null) extends ApiResource
    {
        public function toArray($request): array
        {
            return ['id' => $this->id, 'created_at' => $this->created_at];
        }
    };

    Route::get('/api/v1/__test/categories', fn () => $resource::collection(Category::orderBy('id')->paginate(2)))->middleware('api');
    Route::get('/api/v1/__test/category', fn () => new $resource(Category::first()))->middleware('api');
});

test('paginated lists use the contract meta block and nothing else', function () {
    Category::factory()->count(3)->create();

    $this->getJson('/api/v1/__test/categories')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['*' => ['id', 'created_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ])
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
});

test('single objects are wrapped in data', function () {
    Category::factory()->create();

    $this->getJson('/api/v1/__test/category')
        ->assertOk()
        ->assertExactJsonStructure(['data' => ['id', 'created_at']]);
});

test('dates are ISO 8601 in WIB (+07:00), matching the contract examples', function () {
    Category::factory()->create(['created_at' => '2026-10-10 08:00:00']);

    $this->getJson('/api/v1/__test/category')
        ->assertJsonPath('data.created_at', '2026-10-10T08:00:00+07:00');
});
