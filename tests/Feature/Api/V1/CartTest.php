<?php

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
| CART-01 – CART-06 (docs/api/cart.md)
*/

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->bike = Product::factory()->create(['name' => 'Sepeda Listrik Uwinfly C1 Pro', 'slug' => 'uwinfly-c1-pro', 'min_qty' => 1, 'max_qty' => 2, 'package_weight' => 32000]);
    $this->black = ProductVariant::factory()->for($this->bike)->create(['sku' => 'UWF-HTM', 'price' => 6750000, 'available_qty' => 4, 'package_weight' => 32000]);
    $this->white = ProductVariant::factory()->for($this->bike)->create(['sku' => 'UWF-PTH', 'price' => 7250000, 'available_qty' => 3, 'package_weight' => null]);
    $this->battery = ProductVariant::factory()->for(Product::factory()->create(['min_qty' => null, 'max_qty' => null]))
        ->create(['sku' => 'BAT-20AH', 'price' => 3400000, 'available_qty' => 1, 'package_weight' => 9500]);
});

function inCart(User $user, ProductVariant $variant, int $qty): CartItem
{
    return $user->cartItems()->create(['product_variant_id' => $variant->id, 'qty' => $qty]);
}

// --- CART-01 -------------------------------------------------------------------

test('CART-01 answers the Cart object with live prices, stock, weights, and issues', function () {
    $first = inCart($this->user, $this->black, 1);
    $second = inCart($this->user, $this->battery, 2);

    $response = $this->actingAs($this->user)->getJson('/api/v1/cart')->assertOk();

    expect($response->json('data.items.0'))->toBe([
        'id' => $second->id,
        'variant' => [
            'id' => $this->battery->id, 'sku' => 'BAT-20AH', 'name' => $this->battery->name,
            'variation_values' => $this->battery->variation_values, 'price' => 3400000, 'available_qty' => 1,
            'in_stock' => true, 'package_weight' => 9500, 'image_url' => null,
        ],
        'product' => ['id' => $this->battery->product->id, 'slug' => $this->battery->product->slug, 'name' => $this->battery->product->name, 'min_qty' => null, 'max_qty' => null],
        'qty' => 2,
        'subtotal' => 6800000,
        'weight_total' => 19000,
        'issue' => 'QTY_EXCEEDS_STOCK',
    ])->and($response->json('data.items.1.id'))->toBe($first->id)
        ->and($response->json('data.summary'))->toBe([
            'item_count' => 2, 'total_qty' => 3, 'subtotal' => 13550000, 'total_weight' => 51000, 'has_issue' => true,
        ]);
});

test('CART-01 follows price and stock changes from Jubelio, since nothing is stored in the cart', function () {
    inCart($this->user, $this->black, 2);
    $this->black->update(['price' => 6500000, 'available_qty' => 1]);

    expect($this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.items.0'))
        ->subtotal->toBe(13000000)
        ->issue->toBe('QTY_EXCEEDS_STOCK');
});

test('CART-01 marks issues and keeps problem lines in the cart', function (Closure $break, string $issue) {
    inCart($this->user, $this->black, 1);
    $break($this);

    $item = $this->actingAs($this->user)->getJson('/api/v1/cart')->assertJsonCount(1, 'data.items')->json('data.items.0');

    expect($item['issue'])->toBe($issue);
})->with([
    'stok habis' => [fn ($t) => $t->black->update(['available_qty' => 0]), 'OUT_OF_STOCK'],
    'stok negatif' => [fn ($t) => $t->black->update(['available_qty' => -3]), 'OUT_OF_STOCK'],
    'varian nonaktif' => [fn ($t) => $t->black->update(['is_active' => false]), 'PRODUCT_INACTIVE'],
    'produk nonaktif' => [fn ($t) => $t->bike->update(['is_active' => false]), 'PRODUCT_INACTIVE'],
]);

test('CART-01 leaves inactive lines out of the subtotal and the shipping weight', function () {
    inCart($this->user, $this->black, 1);
    inCart($this->user, $this->battery, 1);
    $this->black->update(['is_active' => false]);

    expect($this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.summary'))
        ->subtotal->toBe(3400000)
        ->total_weight->toBe(9500)
        ->total_qty->toBe(2)
        ->has_issue->toBeTrue();
});

test('CART-01 uses the item group weight for a variant without its own', function () {
    inCart($this->user, $this->white, 2);

    expect($this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.items.0.weight_total'))->toBe(64000);
});

test('CART-01 with an empty cart answers zeros', function () {
    $this->actingAs($this->user)->getJson('/api/v1/cart')->assertExactJson(['data' => [
        'items' => [],
        'summary' => ['item_count' => 0, 'total_qty' => 0, 'subtotal' => 0, 'total_weight' => 0, 'has_issue' => false],
    ]]);
});

test('every cart endpoint except the count requires login', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri, ['variant_id' => 1, 'qty' => 1]), 401, 'UNAUTHENTICATED');
})->with([
    ['GET', '/api/v1/cart'], ['POST', '/api/v1/cart/items'], ['PATCH', '/api/v1/cart/items/1'],
    ['DELETE', '/api/v1/cart/items/1'], ['DELETE', '/api/v1/cart'],
]);

// --- CART-02 -------------------------------------------------------------------

test('CART-02 adds a new line with 201 and the full cart', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->black->id, 'qty' => 1])
        ->assertCreated()
        ->assertJsonPath('message', 'Produk ditambahkan ke keranjang.')
        ->assertJsonPath('data.items.0.variant.id', $this->black->id)
        ->assertJsonPath('data.summary.total_qty', 1);
});

test('CART-02 adding the same variant again raises its qty on the same line with 200', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->battery->id, 'qty' => 1])->assertCreated();
    $this->battery->update(['available_qty' => 5]);

    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->battery->id, 'qty' => 2])
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.qty', 3);

    expect(CartItem::count())->toBe(1);
});

test('CART-02 refuses more than the stock with available_qty, counting what is already in the cart', function () {
    $this->battery->update(['available_qty' => 4]);
    inCart($this->user, $this->battery, 3);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->battery->id, 'qty' => 2]), 409, 'OUT_OF_STOCK')
        ->assertExactJson([
            'message' => 'Stok tidak cukup. Tersisa 4 unit.',
            'code' => 'OUT_OF_STOCK',
            'errors' => ['qty' => ['Maksimal 4 unit untuk varian ini.']],
            'available_qty' => 4,
        ]);

    expect($this->user->cartItems()->sole()->qty)->toBe(3);
});

test('CART-02 refuses a variant with no stock', function () {
    $this->battery->update(['available_qty' => 0]);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->battery->id, 'qty' => 1]), 409, 'OUT_OF_STOCK')
        ->assertJsonPath('available_qty', 0);
});

test('CART-02 counts max_qty over all variants of the same product', function () {
    inCart($this->user, $this->black, 1);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->white->id, 'qty' => 2]), 422, 'QTY_LIMIT_EXCEEDED')
        ->assertExactJson([
            'message' => 'Maksimal pembelian produk ini 2 unit per pesanan.',
            'code' => 'QTY_LIMIT_EXCEEDED',
            'errors' => ['qty' => ['Maksimal 2 unit.']],
            'max_qty' => 2,
        ]);

    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->white->id, 'qty' => 1])->assertCreated();
});

test('CART-02 enforces min_qty', function () {
    $this->bike->update(['min_qty' => 2]);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->black->id, 'qty' => 1]), 422, 'QTY_LIMIT_EXCEEDED')
        ->assertJsonPath('min_qty', 2)
        ->assertJsonPath('errors.qty.0', 'Minimal 2 unit.');
});

test('CART-02 answers VARIANT_NOT_FOUND and VARIANT_INACTIVE', function () {
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => 999999, 'qty' => 1]), 404, 'VARIANT_NOT_FOUND');

    $this->bike->update(['is_active' => false]);
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->black->id, 'qty' => 1]), 422, 'VARIANT_INACTIVE');
});

test('CART-02 validates variant_id and qty', function (array $body, string $field) {
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/cart/items', $body), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
})->with([
    'tanpa variant_id' => [['qty' => 1], 'variant_id'],
    'qty 0' => [['variant_id' => 1, 'qty' => 0], 'qty'],
    'qty bukan angka' => [['variant_id' => 1, 'qty' => 'dua'], 'qty'],
]);

// --- CART-03 -------------------------------------------------------------------

test('CART-03 replaces the qty and answers the cart', function () {
    $item = inCart($this->user, $this->black, 1);

    $this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 2])
        ->assertOk()
        ->assertJsonPath('data.items.0.qty', 2)
        ->assertJsonPath('data.items.0.subtotal', 13500000)
        ->assertJsonMissingPath('message');
});

test('CART-03 lowering qty to the stock clears QTY_EXCEEDS_STOCK', function () {
    $item = inCart($this->user, $this->battery, 3);

    $this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 1])
        ->assertOk()
        ->assertJsonPath('data.items.0.issue', null)
        ->assertJsonPath('data.summary.has_issue', false);
});

test('CART-03 refuses more than the stock and keeps the old qty', function () {
    $item = inCart($this->user, $this->battery, 1);

    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 2]), 409, 'OUT_OF_STOCK');
    expect($item->fresh()->qty)->toBe(1);
});

test('CART-03 checks max_qty against the other variants only, not the line itself twice', function () {
    $item = inCart($this->user, $this->black, 2);
    $this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 2])->assertOk();

    inCart($this->user, $this->white, 1);

    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 2]), 422, 'QTY_LIMIT_EXCEEDED');
    $this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 1])->assertOk();
});

test('CART-03 on an inactive variant answers VARIANT_INACTIVE (it can only be removed)', function () {
    $item = inCart($this->user, $this->black, 1);
    $this->black->update(['is_active' => false]);

    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/cart/items/{$item->id}", ['qty' => 1]), 422, 'VARIANT_INACTIVE');
});

test('CART-03 and CART-04 on another user\'s line answer 404, not 403', function (string $method) {
    $other = inCart(User::factory()->create(), $this->black, 1);

    assertApiError($this->actingAs($this->user)->json($method, "/api/v1/cart/items/{$other->id}", ['qty' => 1]), 404, 'CART_ITEM_NOT_FOUND')
        ->assertJsonPath('message', 'Item keranjang tidak ditemukan.');
    expect($other->fresh())->not->toBeNull();
})->with(['PATCH', 'DELETE']);

// --- CART-04 / CART-05 ---------------------------------------------------------

test('CART-04 removes a line, inactive ones too', function () {
    $item = inCart($this->user, $this->black, 1);
    $this->bike->update(['is_active' => false]);

    $this->actingAs($this->user)->deleteJson("/api/v1/cart/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Produk dihapus dari keranjang.')
        ->assertJsonPath('data.items', []);

    expect(CartItem::count())->toBe(0);
});

test('CART-05 empties only this user\'s cart and is fine when already empty', function () {
    inCart($this->user, $this->black, 1);
    inCart($this->user, $this->battery, 1);
    $others = inCart(User::factory()->create(), $this->black, 1);

    $this->actingAs($this->user)->deleteJson('/api/v1/cart')
        ->assertOk()
        ->assertExactJson(['data' => [
            'items' => [],
            'summary' => ['item_count' => 0, 'total_qty' => 0, 'subtotal' => 0, 'total_weight' => 0, 'has_issue' => false],
        ], 'message' => 'Keranjang dikosongkan.']);

    expect(CartItem::pluck('id')->all())->toBe([$others->id]);
    $this->actingAs($this->user)->deleteJson('/api/v1/cart')->assertOk();
});

// --- CART-06 -------------------------------------------------------------------

test('CART-06 gives guests 0 and never 401', function () {
    fromFrontend()->getJson('/api/v1/cart/count')->assertOk()->assertExactJson(['data' => ['total_qty' => 0, 'has_issue' => false]]);
});

test('CART-06 matches summary.total_qty and has_issue of CART-01', function (Closure $setup) {
    $setup($this);

    $cart = $this->actingAs($this->user)->getJson('/api/v1/cart')->json('data.summary');
    $count = $this->actingAs($this->user)->getJson('/api/v1/cart/count')->assertOk()->json('data');

    expect($count)->toBe(['total_qty' => $cart['total_qty'], 'has_issue' => $cart['has_issue']]);
})->with([
    'kosong' => [fn () => null],
    'normal' => [fn ($t) => inCart($t->user, $t->black, 2)],
    'lebih dari stok' => [fn ($t) => inCart($t->user, $t->battery, 3)],
    'stok negatif' => [function ($t) {
        inCart($t->user, $t->battery, 1);
        $t->battery->update(['available_qty' => -1]);
    }],
    'produk nonaktif' => [function ($t) {
        inCart($t->user, $t->black, 1);
        $t->bike->update(['is_active' => false]);
    }],
]);

test('CART-06 for a logged-in browser session reads the session user', function () {
    inCart($this->user, $this->black, 2);
    fromFrontend()->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertOk();

    fromFrontend()->getJson('/api/v1/cart/count')->assertJsonPath('data.total_qty', 2);
});

test('CART-06 runs in a single query without loading products', function () {
    inCart($this->user, $this->black, 1);
    inCart($this->user, $this->battery, 1);
    $this->actingAs($this->user);

    DB::enableQueryLog();
    $this->getJson('/api/v1/cart/count')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')
        ->reject(fn ($sql) => str_contains($sql, '`cache`'));

    expect($queries)->toHaveCount(1)
        ->and(strtolower($queries->first()))->toContain('sum(cart_items.qty)')->not->toContain('`product_images`');
});
