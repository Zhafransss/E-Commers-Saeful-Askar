<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/*
| Helpers for the black-box suite (tests/Feature/BlackBox). The tests treat the app as a closed box:
| they only send HTTP requests and read responses/DB rows, as specified in docs/api.
*/

/** A product with active variants; $variants is a list of attribute arrays (price, available_qty, ...). */
function bbProduct(array $product = [], array $variants = [[]], ?Category $category = null): Product
{
    $model = Product::factory()->create($product + ['category_id' => $category?->id ?? Category::factory()->create()->id]);

    foreach ($variants as $attributes) {
        ProductVariant::factory()->for($model)->create($attributes);
    }

    return $model->refresh();
}

function bbCustomer(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function bbAdmin(): User
{
    return User::factory()->admin()->create();
}

/** Every contract error response has exactly these keys at the top. */
function bbExpectErrorShape($response): void
{
    expect(array_keys($response->json()))->toContain('message', 'code');
}
