<?php

use App\Enums\SyncTrigger;
use App\Enums\WebhookEventStatus;
use App\Exceptions\JubelioApiException;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
| WH-02 update-product, WH-03 update-price, WH-04 update-qty (docs/api/webhooks.md),
| end to end through the real routes. The test queue is `sync`, so the stored
| event is processed during the request, as a worker would right after.
*/

beforeEach(function () {
    fakeJubelioCatalog();
    config(['jubelio.webhook_secret' => 'rahasia-webhook']);
});

/**
 * @param  array<string, mixed>|string  $body  Array payloads are JSON-encoded
 */
function deliverJubelio(string $route, array|string $body, ?string $signature = null): TestResponse
{
    $raw = is_string($body) ? $body : json_encode($body);

    return test()->call('POST', "/webhooks/jubelio/{$route}", [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_SIGN' => $signature ?? jubelioSignature($raw),
    ], $raw);
}

// --- WH-02 -----------------------------------------------------------------------

test('WH-02 syncs the item group and records the event as processed', function () {
    deliverJubelio('product', ['action' => 'update-product', 'item_group_id' => 18, 'item_group_name' => 'Baterai Lithium Phylion'])
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    expect(Product::where('jubelio_item_group_id', 18)->sole())
        ->slug->toBe('baterai-lithium-phylion')
        ->is_active->toBeTrue()
        ->and(ProductVariant::where('sku', 'SC000384N')->sole()->available_qty)->toBe(3)
        ->and(WebhookEvent::sole())
        ->event_type->toBe('update-product')
        ->status->toBe(WebhookEventStatus::Processed)
        ->and(SyncLog::sole()->trigger)->toBe(SyncTrigger::Webhook);
});

test('WH-02 delivered twice is stored and processed once', function () {
    $body = ['action' => 'update-product', 'item_group_id' => 18, 'item_group_name' => 'Baterai Lithium Phylion'];

    deliverJubelio('product', $body)->assertOk();
    deliverJubelio('product', $body)->assertOk();

    expect(WebhookEvent::count())->toBe(1)
        ->and(SyncLog::count())->toBe(1)
        ->and(collect(jubelioCalls())->filter(fn ($path) => $path === '/inventory/items/group/18'))->toHaveCount(1);
});

test('WH-02 for a group removed in Jubelio deactivates the product', function () {
    Product::factory()->create(['jubelio_item_group_id' => 77]);

    deliverJubelio('product', ['action' => 'update-product', 'item_group_id' => 77])->assertOk();

    expect(Product::where('jubelio_item_group_id', 77)->sole()->is_active)->toBeFalse()
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Processed);
});

test('WH-02 with a wrong signature is rejected and nothing is synced', function () {
    assertApiError(deliverJubelio('product', ['action' => 'update-product', 'item_group_id' => 18], 'salah'), 401, 'INVALID_SIGNATURE');

    expect(Product::count())->toBe(0)
        ->and(WebhookEvent::sole()->signature_valid)->toBeFalse();
});

test('WH-02 and WH-03 without item_group_id answer 422', function (string $route) {
    assertApiError(deliverJubelio($route, ['action' => 'x']), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('item_group_id');
})->with(['product', 'price']);

// --- WH-03 -----------------------------------------------------------------------

test('WH-03 re-reads the price of an existing product', function () {
    deliverJubelio('product', ['action' => 'update-product', 'item_group_id' => 18])->assertOk();

    $repriced = fixture('jubelio/catalog/item-group-18.json');
    $repriced['product_skus'][1]['sell_price'] = '4250000.0000';
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response($repriced)]);

    deliverJubelio('price', fixtureContent('jubelio/webhooks/update-price.json'))->assertOk()->assertExactJson(['status' => 'ok']);

    expect(ProductVariant::where('sku', 'SC000384N')->value('price'))->toBe(4250000)
        ->and(WebhookEvent::where('event_type', 'update-price')->sole()->status)->toBe(WebhookEventStatus::Processed);
});

test('WH-03 for a group the website does not have yet creates it in full', function () {
    deliverJubelio('price', fixtureContent('jubelio/webhooks/update-price.json'))->assertOk();

    expect(Product::where('jubelio_item_group_id', 18)->sole()->variants)->toHaveCount(2);
});

// --- WH-04 -----------------------------------------------------------------------

test('WH-04 refreshes stock of the listed items from the website warehouse', function () {
    $big = ProductVariant::factory()->create(['sku' => 'SC000384N', 'jubelio_item_id' => 19, 'available_qty' => 50]);
    $small = ProductVariant::factory()->create(['sku' => 'SC000381N', 'jubelio_item_id' => 20, 'available_qty' => 50]);

    deliverJubelio('stock', fixtureContent('jubelio/webhooks/update-qty.json'))->assertOk()->assertExactJson(['status' => 'ok']);

    expect($big->fresh()->available_qty)->toBe(3)
        ->and($small->fresh()->available_qty)->toBe(2)
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Processed)
        ->and(SyncLog::sole()->items_processed)->toBe(2);
});

test('WH-04 for another warehouse is ignored without calling Jubelio', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'SC000384N', 'jubelio_item_id' => 19, 'available_qty' => 50]);

    deliverJubelio('stock', ['action' => 'update-qty', 'item_group_id' => 18, 'item_ids' => [19], 'location_id' => 0])->assertOk();

    expect($variant->fresh()->available_qty)->toBe(50)
        ->and(WebhookEvent::sole())
        ->status->toBe(WebhookEventStatus::Ignored)
        ->error->toBe('Stok berubah di gudang lain (location_id 0).')
        ->and(jubelioCalls())->toBeEmpty();
});

test('WH-04 for items the website does not know yet syncs their item group in full', function () {
    deliverJubelio('stock', fixtureContent('jubelio/webhooks/update-qty.json'))->assertOk();

    expect(Product::where('jubelio_item_group_id', 18)->exists())->toBeTrue()
        ->and(ProductVariant::where('sku', 'SC000384N')->value('available_qty'))->toBe(3)
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Processed);
});

test('WH-04 for unknown items without an item group is ignored', function () {
    deliverJubelio('stock', ['action' => 'update-qty', 'item_ids' => [12345], 'location_id' => -1])->assertOk();

    expect(WebhookEvent::sole())
        ->status->toBe(WebhookEventStatus::Ignored)
        ->error->toBe('Item belum ada di katalog website.');
});

test('WH-04 without item_ids or location_id answers 422', function (array $body, string $field) {
    assertApiError(deliverJubelio('stock', $body), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors($field);
})->with([
    'item_ids kosong' => [['action' => 'update-qty', 'item_ids' => [], 'location_id' => -1], 'item_ids'],
    'tanpa location_id' => [['action' => 'update-qty', 'item_ids' => [19]], 'location_id'],
]);

// --- Failures ------------------------------------------------------------------

test('a Jubelio outage leaves the event received so the queue retries it', function () {
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response(fixture('jubelio/error-sql.json'), 500)]);
    $event = WebhookEvent::factory()->create(['payload' => ['action' => 'update-product', 'item_group_id' => 18]]);

    expect(fn () => (new ProcessJubelioWebhookJob($event->id))->handle())->toThrow(JubelioApiException::class);

    expect($event->fresh()->status)->toBe(WebhookEventStatus::Received);
});
