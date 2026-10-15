<?php

use App\Http\Resources\ApiResource;
use App\Models\Article;
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
            return ['id' => $this->id, 'published_at' => $this->published_at];
        }
    };

    Route::get('/api/v1/__test/articles', fn () => $resource::collection(Article::orderBy('id')->paginate(2)))->middleware('api');
    Route::get('/api/v1/__test/article', fn () => new $resource(Article::first()))->middleware('api');
});

test('paginated lists use the contract meta block and nothing else', function () {
    Article::factory()->count(3)->create();

    $this->getJson('/api/v1/__test/articles')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['*' => ['id', 'published_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ])
        ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
});

test('single objects are wrapped in data', function () {
    Article::factory()->create();

    $this->getJson('/api/v1/__test/article')
        ->assertOk()
        ->assertExactJsonStructure(['data' => ['id', 'published_at']]);
});

test('dates are ISO 8601 in WIB (+07:00), matching the contract examples', function () {
    Article::factory()->create(['published_at' => '2026-10-10 08:00:00']);

    $this->getJson('/api/v1/__test/article')
        ->assertJsonPath('data.published_at', '2026-10-10T08:00:00+07:00');
});
