<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Jobs\Jubelio\SyncProductGroupJob;
use App\Jobs\Jubelio\SyncStockJob;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Services\Jubelio\StockSyncService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

/*
| Stock reconciliation (INT-JB-06), the three sync commands with their
| sync_logs rows and schedule, and the queue jobs named by the contract.
*/

beforeEach(fn () => fakeJubelioCatalog());

function variantWithStock(string $sku, int $jubelioItemId, int $available): ProductVariant
{
    return ProductVariant::factory()->create(['sku' => $sku, 'jubelio_item_id' => $jubelioItemId, 'available_qty' => $available, 'stock_synced_at' => null]);
}

// --- Reconciliation ------------------------------------------------------------

test('reconciliation copies warehouse stock for every known variant and skips unknown items', function () {
    $battery = variantWithStock('SC000384N', 19, 50);
    $socket = variantWithStock('SD000383N', 21, 7);
    $bundle = variantWithStock('72V24Ah-10A-M23', 29, 1);

    expect(app(StockSyncService::class)->reconcile())->toBe(3);

    expect($battery->fresh())->available_qty->toBe(3)->stock_synced_at->not->toBeNull()
        ->and($socket->fresh()->available_qty)->toBe(0)
        ->and($bundle->fresh()->available_qty)->toBe(-2)
        ->and(ProductVariant::where('jubelio_item_id', 22)->exists())->toBeFalse();
});

test('reconciliation reads every stock page', function () {
    $all = fixture('jubelio/catalog/inventory.json');
    fakeJubelioCatalog(['/inventory/' => function ($request) use ($all) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = array_slice($all['data'], ((int) $query['page'] - 1) * 2, 2);

        return Http::response(['data' => $page, 'totalCount' => count($all['data'])]);
    }]);
    $bundle = variantWithStock('72V24Ah-10A-M23', 29, 1);

    app(StockSyncService::class)->reconcile();

    expect($bundle->fresh()->available_qty)->toBe(-2)
        ->and(collect(jubelioCalls())->filter(fn ($path) => $path === '/inventory/'))->toHaveCount(3);
});

test('stock sync refuses to run without a warehouse in .env', function () {
    config(['jubelio.location_id' => null]);
    variantWithStock('SC000384N', 19, 5);

    expect(fn () => app(StockSyncService::class)->reconcile())->toThrow(RuntimeException::class, 'JUBELIO_LOCATION_ID');
});

// --- Commands ------------------------------------------------------------------

test('each command records a successful sync_logs row with the item count', function (string $command, SyncType $type, int $items) {
    if ($type === SyncType::Stock) {
        variantWithStock('SC000384N', 19, 0);
        variantWithStock('SC000381N', 20, 0);
    }

    $this->artisan($command)->assertSuccessful();

    expect(SyncLog::sole())
        ->type->toBe($type)
        ->trigger->toBe(SyncTrigger::Schedule)
        ->status->toBe(SyncStatus::Success)
        ->items_processed->toBe($items)
        ->finished_at->not->toBeNull();
})->with([
    'kategori' => ['jubelio:sync-categories', SyncType::Categories, 4],
    'produk penuh' => ['jubelio:sync-products --full', SyncType::Products, 3],
    'produk perubahan (pertama kali = penuh)' => ['jubelio:sync-products', SyncType::Products, 3],
    'stok' => ['jubelio:reconcile-stock', SyncType::Stock, 2],
]);

test('a failing Jubelio call fails the command and keeps Jubelio\'s message in sync_logs', function () {
    fakeJubelioCatalog(['/inventory/items/' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    $this->artisan('jubelio:sync-products --full')->assertFailed();

    expect(SyncLog::sole())
        ->status->toBe(SyncStatus::Failed)
        ->message->toContain('/inventory/items/')->toContain('HTTP 500');
});

test('the catalog schedule matches PRD §4', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event) => [trim(preg_replace('/^.*artisan.? /', '', $event->command)) => $event->expression]);

    expect($events->all())->toMatchArray([
        'jubelio:sync-categories' => '0 2 * * *',
        'jubelio:sync-products --full' => '30 2 * * *',
        'jubelio:sync-products' => '*/30 * * * *',
        'jubelio:reconcile-stock' => '*/15 * * * *',
    ]);
});

// --- Queue jobs ----------------------------------------------------------------

test('SyncProductGroupJob syncs one item group and logs it', function () {
    SyncProductGroupJob::dispatch(18);

    expect(Product::where('jubelio_item_group_id', 18)->exists())->toBeTrue()
        ->and(SyncLog::sole())->type->toBe(SyncType::Products)->trigger->toBe(SyncTrigger::Manual)->items_processed->toBe(1);
});

test('SyncStockJob refreshes the given items and logs it', function () {
    $battery = variantWithStock('SC000384N', 19, 50);

    SyncStockJob::dispatch([19]);

    expect($battery->fresh()->available_qty)->toBe(3)
        ->and(SyncLog::sole())->type->toBe(SyncType::Stock)->items_processed->toBe(1);
});

test('both jobs use the integration retry policy', function () {
    foreach ([new SyncProductGroupJob(18), new SyncStockJob([19])] as $job) {
        expect($job->tries)->toBe(6)->and($job->backoff())->toBe([10, 30, 120, 300, 900]);
    }
});
