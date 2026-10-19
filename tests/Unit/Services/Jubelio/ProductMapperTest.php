<?php

use App\Services\Jubelio\Mappers\ProductMapper;
use App\Support\HtmlSanitizer;

/*
| INT-JB-04 / INT-JB-05 mapping, with the formats seen on the store's real
| Jubelio account: decimal strings, min/max 0, empty prices[], integer ids.
*/

beforeEach(function () {
    $this->mapper = new ProductMapper(new HtmlSanitizer, '-102');
    $this->group = fixture('jubelio/catalog/item-group-18.json');
    $this->skuRows = collect(fixture('jubelio/catalog/variations.json')['data'])->keyBy('item_id')->all();
});

test('decimal strings are rounded to whole numbers; empty, zero, and junk become null', function (mixed $value, ?int $expected) {
    expect($this->mapper->positiveInt($value))->toBe($expected);
})->with([
    ['200.0000', 200],
    ['27500.0000', 27500],
    ['99.6', 100],
    [8800, 8800],
    ['0.0000', null],
    [null, null],
    ['', null],
    ['abc', null],
]);

test('product columns follow the real Jubelio formats', function () {
    $product = $this->mapper->product($this->group);

    expect($product)
        ->name->toBe('Baterai Lithium Phylion')
        ->brand_name->toBeNull()
        ->sell_unit->toBe('Buah')
        ->package_weight->toBe(8800)
        ->package_length->toBe(35)
        ->package_width->toBe(15)
        ->package_height->toBe(20)
        ->variations->toBe([['label' => 'Kapasitas', 'values' => ['48V 12Ah', '72V 24Ah']]])
        ->is_active->toBeTrue()
        ->and($product['jubelio_last_modified']->toIso8601String())->toBe('2026-10-13T13:41:03+07:00');
});

test('min and max 0 mean no limit and become null, so max_qty never blocks every purchase', function () {
    expect($this->mapper->product($this->group))
        ->min_qty->toBeNull()
        ->max_qty->toBeNull();

    expect($this->mapper->product(['min' => 1, 'max' => 2] + $this->group))
        ->min_qty->toBe(1)
        ->max_qty->toBe(2);
});

test('the description is sanitized before it is stored', function () {
    $html = $this->mapper->product($this->group)['description_html'];

    expect($html)->toContain('<strong>Baterai Lithium Phylion</strong>')
        ->not->toContain('<script')
        ->not->toContain('alert');
});

test('inactive or not-for-sale item groups are not sellable', function (array $flags) {
    expect($this->mapper->isSellable($flags + $this->group))->toBeFalse()
        ->and($this->mapper->product($flags + $this->group)['is_active'])->toBeFalse();
})->with([
    'nonaktif' => [['is_active' => false]],
    'tidak dijual' => [['sell_this' => false]],
]);

test('the price is the website store price when set, otherwise the SKU sell_price', function () {
    $sku = ['sell_price' => '75000.0000', 'prices' => [
        ['store_id' => '99936', 'sell_price' => '80000.0000'],
        ['store_id' => '-102', 'sell_price' => '70000.0000'],
    ]];

    expect($this->mapper->price($sku))->toBe(70000)
        ->and($this->mapper->price(['sell_price' => '75000.0000', 'prices' => []]))->toBe(75000)
        ->and($this->mapper->price(['sell_price' => '75000.0000']))->toBe(75000)
        ->and((new ProductMapper(new HtmlSanitizer, null))->price($sku))->toBe(75000);
});

test('variants take name, weight, and bundle flag from INT-JB-05', function () {
    $variants = $this->mapper->variants($this->group, $this->skuRows);

    expect(array_keys($variants))->toBe([20, 19])
        ->and($variants[19])->toMatchArray([
            'jubelio_item_id' => 19,
            'sku' => 'SC000384N',
            'name' => 'Baterai Lithium Phylion 72V 24Ah',
            'variation_values' => [['label' => 'Kapasitas', 'value' => '72V 24Ah']],
            'price' => 4500000,
            'package_weight' => 27500,
            'barcode' => null,
            'image_url' => 'https://cdn.example.com/images/18-phylion-24ah.png',
            'is_bundle' => false,
        ])
        ->and($variants[20])->toMatchArray(['package_weight' => 3000, 'barcode' => '8991234500011']);
});

test('a SKU missing from INT-JB-05 gets a name built from its variation and no weight of its own', function () {
    $variants = $this->mapper->variants($this->group, []);

    expect($variants[20])
        ->name->toBe('Baterai Lithium Phylion - 48V 12Ah')
        ->package_weight->toBeNull()
        ->is_bundle->toBeFalse();
});

test('empty variation lists become null', function () {
    $group = fixture('jubelio/catalog/item-group-19.json');

    expect($this->mapper->product($group)['variations'])->toBeNull()
        ->and($this->mapper->variants($group, [])[21]['variation_values'])->toBeNull();
});

test('images follow the SKU order and sequence_number; single-SKU images of a multi-SKU product belong to that variant', function () {
    $images = $this->mapper->images($this->group);

    expect(array_column($images, 'jubelio_image_id'))->toBe([301, 302, 303])
        ->and(array_column($images, 'jubelio_item_id'))->toBe([20, 19, 19])
        ->and(array_column($images, 'sort_order'))->toBe([0, 1, 2])
        ->and($images[1])->toMatchArray([
            'url' => 'https://cdn.example.com/images/18-phylion-24ah.png',
            'thumbnail_url' => 'https://cdn.example.com/images/18-phylion-24ah-thumb.png',
        ])
        ->and($this->mapper->product($this->group)['thumbnail_url'])->toBe('https://cdn.example.com/images/18-phylion-12ah-thumb.png');
});

test('images of a single-SKU product, or shared by several SKUs, belong to the whole product', function () {
    expect($this->mapper->images(fixture('jubelio/catalog/item-group-19.json'))[0]['jubelio_item_id'])->toBeNull();

    $shared = $this->group;
    $shared['product_skus'][1]['images'][] = $shared['product_skus'][0]['images'][0];

    expect(collect($this->mapper->images($shared))->firstWhere('jubelio_image_id', 301)['jubelio_item_id'])->toBeNull();
});
