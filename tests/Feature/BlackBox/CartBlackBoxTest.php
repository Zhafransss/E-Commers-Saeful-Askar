<?php

use App\Models\Product;
use App\Models\ProductVariant;

/*
| Black-box: CART-01..05 (docs/api/cart.md): quantity boundaries, stock, ownership, and the cart-count endpoint.
*/

beforeEach(function () {
    $this->user = bbCustomer();
    $this->variant = ProductVariant::factory()->for(Product::factory()->create())->create(['price' => 100000, 'available_qty' => 5, 'package_weight' => 2000]);
    $this->add = fn (array $body) => $this->actingAs($this->user)->postJson('/api/v1/cart/items', $body);
});

test('adding a variant creates a line (201); adding it again merges the quantity (200)', function () {
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 2])->assertCreated()->assertJsonPath('data.summary.total_qty', 2);
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 1])->assertOk()->assertJsonPath('data.items.0.qty', 3)->assertJsonPath('data.summary.item_count', 1);
});

test('quantity boundaries: 1 and 9999 are valid input, 0, -1, 10000, decimals and text are not', function (mixed $qty, int $status) {
    expect(($this->add)(['variant_id' => $this->variant->id, 'qty' => $qty])->getStatusCode())->toBe($status);
})->with([
    'zero' => [0, 422], 'negative' => [-1, 422], 'one' => [1, 201], 'five (all stock)' => [5, 201],
    'one over stock' => [6, 409], 'max input 9999 exceeds stock' => [9999, 409], 'over max input' => [10000, 422],
    'decimal' => [1.5, 422], 'text' => ['abc', 422], 'null' => [null, 422],
]);

test('stock is checked against the merged quantity, and an exhausted variant says so', function () {
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 4])->assertCreated();

    assertApiError(($this->add)(['variant_id' => $this->variant->id, 'qty' => 2]), 409, 'OUT_OF_STOCK')
        ->assertJsonPath('available_qty', 5);
    $this->variant->update(['available_qty' => 0]);
    assertApiError(($this->add)(['variant_id' => $this->variant->id, 'qty' => 1]), 409, 'OUT_OF_STOCK');
});

test('unknown, inactive, and zero variant ids are refused with the right code', function () {
    assertApiError(($this->add)(['variant_id' => 999999, 'qty' => 1]), 404, 'VARIANT_NOT_FOUND');
    assertApiError(($this->add)(['variant_id' => 0, 'qty' => 1]), 422, 'VALIDATION_ERROR');
    assertApiError(($this->add)(['qty' => 1]), 422, 'VALIDATION_ERROR');

    $this->variant->update(['is_active' => false]);
    assertApiError(($this->add)(['variant_id' => $this->variant->id, 'qty' => 1]), 422, 'VARIANT_INACTIVE');
    $this->variant->update(['is_active' => true]);
    $this->variant->product->update(['is_active' => false]);
    assertApiError(($this->add)(['variant_id' => $this->variant->id, 'qty' => 1]), 422, 'VARIANT_INACTIVE');
});

test('product min/max quantity rules are enforced per product, across its variants', function () {
    $product = Product::factory()->create(['min_qty' => 2, 'max_qty' => 3]);
    $one = ProductVariant::factory()->for($product)->create(['available_qty' => 50]);
    $two = ProductVariant::factory()->for($product)->create(['available_qty' => 50]);

    assertApiError(($this->add)(['variant_id' => $one->id, 'qty' => 1]), 422, 'QTY_LIMIT_EXCEEDED')->assertJsonPath('min_qty', 2);
    ($this->add)(['variant_id' => $one->id, 'qty' => 2])->assertCreated();
    assertApiError(($this->add)(['variant_id' => $two->id, 'qty' => 2]), 422, 'QTY_LIMIT_EXCEEDED')->assertJsonPath('max_qty', 3);
});

test('update sets the quantity (not adds), delete removes a line, clear empties the cart', function () {
    $id = ($this->add)(['variant_id' => $this->variant->id, 'qty' => 2])->json('data.items.0.id');

    $this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$id}", ['qty' => 4])->assertOk()->assertJsonPath('data.items.0.qty', 4)->assertJsonPath('data.items.0.subtotal', 400000);
    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$id}", ['qty' => 6]), 409, 'OUT_OF_STOCK');
    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$id}", ['qty' => 0]), 422, 'VALIDATION_ERROR');

    $this->actingAs($this->user)->deleteJson("/api/v1/cart/items/{$id}")->assertOk()->assertJsonPath('data.items', []);
    assertApiError($this->actingAs($this->user)->deleteJson("/api/v1/cart/items/{$id}"), 404, 'CART_ITEM_NOT_FOUND');

    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 1]);
    $this->actingAs($this->user)->deleteJson('/api/v1/cart')->assertOk()->assertJsonPath('data.summary.item_count', 0);
});

test('nobody can read, change or delete another user\'s cart line (404, never 403/200)', function () {
    $other = bbCustomer();
    $line = $other->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 2]);

    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$line->id}", ['qty' => 1]), 404, 'CART_ITEM_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->deleteJson("/api/v1/cart/items/{$line->id}"), 404, 'CART_ITEM_NOT_FOUND');
    expect($this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.items'))->toBe([])
        ->and($line->fresh()->qty)->toBe(2);
});

test('the cart totals add up: subtotal = Σ price × qty, weight = Σ weight × qty, inactive lines flagged and excluded from payable totals', function () {
    $second = ProductVariant::factory()->for(Product::factory()->create())->create(['price' => 250000, 'available_qty' => 9, 'package_weight' => 500]);
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 2]);
    ($this->add)(['variant_id' => $second->id, 'qty' => 3]);

    $summary = $this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.summary');
    expect($summary)->toMatchArray(['item_count' => 2, 'total_qty' => 5, 'subtotal' => 2 * 100000 + 3 * 250000, 'total_weight' => 2 * 2000 + 3 * 500, 'has_issue' => false]);

    $second->update(['available_qty' => 1]);
    $cart = $this->actingAs($this->user)->getJson('/api/v1/cart')->json('data');
    expect(collect($cart['items'])->pluck('issue')->filter()->count())->toBe(1)->and($cart['summary']['has_issue'])->toBeTrue();
});

test('cart count: public (0 for guests), counts quantity for members and flags issues', function () {
    $this->getJson('/api/v1/cart/count')->assertOk()->assertExactJson(['data' => ['total_qty' => 0, 'has_issue' => false]]);

    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 3]);
    $this->actingAs($this->user)->getJson('/api/v1/cart/count')->assertOk()->assertJsonPath('data.total_qty', 3)->assertJsonPath('data.has_issue', false);

    $this->variant->update(['available_qty' => 1]);
    $this->actingAs($this->user)->getJson('/api/v1/cart/count')->assertJsonPath('data.has_issue', true);
});

test('every cart endpoint but the count needs a login', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri, ['variant_id' => 1, 'qty' => 1]), 401, 'UNAUTHENTICATED');
})->with([['GET', '/api/v1/cart'], ['POST', '/api/v1/cart/items'], ['PATCH', '/api/v1/cart/items/1'], ['DELETE', '/api/v1/cart/items/1'], ['DELETE', '/api/v1/cart']]);

test('two concurrent-looking adds never exceed stock', function () {
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 3])->assertCreated();
    ($this->add)(['variant_id' => $this->variant->id, 'qty' => 3])->assertStatus(409);

    expect((int) $this->user->cartItems()->sum('qty'))->toBe(3);
});
