<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use App\Jobs\Jubelio\RunManualSyncJob;
use App\Models\Category;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| ADM-13 and ADM-14 (docs/api/admin.md): start a sync by hand, and read the history.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->start = fn (array $body) => $this->actingAs($this->admin)->postJson('/api/v1/admin/sync', $body);
});

test('ADM-13 and ADM-14 refuse guests and customers', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs(User::factory()->create())->json($method, $uri), 403, 'FORBIDDEN');
})->with([['POST', '/api/v1/admin/sync'], ['GET', '/api/v1/admin/sync-logs']]);

test('ADM-13 starts a sync in the queue and answers with its sync log', function (string $type, string $label) {
    Queue::fake();

    $response = ($this->start)(['type' => $type])->assertStatus(202);

    $log = SyncLog::sole();
    expect($response->json('data'))->toMatchArray(['sync_log_id' => $log->id, 'type' => $type, 'status' => 'running'])
        ->and($response->json('message'))->toBe("Sinkron {$label} dimulai.")
        ->and($log)->status->toBe(SyncStatus::Running)->trigger->toBe(SyncTrigger::Manual);
    Queue::assertPushed(RunManualSyncJob::class, fn ($job) => $job->type === $type && $job->syncLogId === $log->id);
})->with([['categories', 'kategori'], ['products', 'produk'], ['stock', 'stok'], ['full', 'penuh']]);

test('ADM-13 passes the "full" choice for products', function () {
    Queue::fake();

    ($this->start)(['type' => 'products', 'full' => true])->assertStatus(202);

    Queue::assertPushed(RunManualSyncJob::class, fn ($job) => $job->fullProducts === true);
});

test('ADM-13 allows one sync of a kind at a time and names the one that runs', function () {
    Queue::fake();
    $first = ($this->start)(['type' => 'products'])->assertStatus(202)->json('data.sync_log_id');

    $response = ($this->start)(['type' => 'products']);

    assertApiError($response, 409, 'SYNC_ALREADY_RUNNING');
    expect($response->json('sync_log_id'))->toBe($first)
        ->and($response->json('message'))->toStartWith('Sinkron produk masih berjalan sejak ')->toEndWith('. Tunggu hingga selesai.')
        ->and(SyncLog::count())->toBe(1);

    // Another kind is free.
    ($this->start)(['type' => 'stock'])->assertStatus(202);
});

test('ADM-13 "full" needs all three kinds free, and takes nothing when one is busy', function () {
    Queue::fake();
    $busy = Cache::lock('sync:stock', 60);
    expect($busy->get())->toBeTrue();

    assertApiError(($this->start)(['type' => 'full']), 409, 'SYNC_ALREADY_RUNNING');

    expect(Cache::lock('sync:categories', 5)->get())->toBeTrue()->and(Cache::lock('sync:products', 5)->get())->toBeTrue()
        ->and(SyncLog::count())->toBe(0);
});

test('ADM-13 rejects an unknown type', function () {
    assertApiError(($this->start)(['type' => 'semua']), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors('type');
    assertApiError(($this->start)([]), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors('type');
});

test('the sync job runs the work, closes its log, and lets the lock go', function () {
    fakeJubelioCatalog();
    $lock = Cache::lock('sync:categories', 600);
    $lock->get();
    $log = SyncLog::start(SyncType::Categories, SyncTrigger::Manual);

    dispatch_sync(new RunManualSyncJob('categories', false, $log->id, [['name' => 'sync:categories', 'owner' => $lock->owner()]]));

    expect($log->fresh())->status->toBe(SyncStatus::Success)->items_processed->toBeGreaterThan(0)
        ->and(Category::count())->toBeGreaterThan(0)
        ->and(Cache::lock('sync:categories', 5)->get())->toBeTrue();
});

test('"full" writes one sync log for each of categories, products, and stock', function () {
    fakeJubelioCatalog();
    $locks = collect(['categories', 'products', 'stock'])->map(function ($kind) {
        $lock = Cache::lock("sync:{$kind}", 600);
        $lock->get();

        return ['name' => "sync:{$kind}", 'owner' => $lock->owner()];
    })->all();
    $log = SyncLog::start(SyncType::Categories, SyncTrigger::Manual);

    dispatch_sync(new RunManualSyncJob('full', false, $log->id, $locks));

    expect(SyncLog::orderBy('id')->get()->map(fn ($l) => [$l->type->value, $l->status->value])->all())
        ->toBe([['categories', 'success'], ['products', 'success'], ['stock', 'success']]);
});

test('a failed sync closes its log as failed and still lets the lock go', function () {
    fakeJubelioCatalog(['/inventory/categories/item-categories/' => Http::response(['message' => 'boom'], 500)]);
    $lock = Cache::lock('sync:categories', 600);
    $lock->get();
    $log = SyncLog::start(SyncType::Categories, SyncTrigger::Manual);

    expect(fn () => dispatch_sync(new RunManualSyncJob('categories', false, $log->id, [['name' => 'sync:categories', 'owner' => $lock->owner()]])))->toThrow(IntegrationException::class);

    expect($log->fresh()->status)->toBe(SyncStatus::Failed)->and(Cache::lock('sync:categories', 5)->get())->toBeTrue();
});

// --- ADM-14 ----------------------------------------------------------------------------------

test('ADM-14 lists the history newest first with the duration, and filters by type and status', function () {
    $old = SyncLog::start(SyncType::Products, SyncTrigger::Schedule);
    $old->forceFill(['started_at' => now()->subHour()])->save();
    $old->forceFill(['started_at' => now()->subHour(), 'finished_at' => now()->subHour()->addSeconds(72), 'status' => SyncStatus::Success, 'items_processed' => 25])->save();
    $failed = SyncLog::start(SyncType::OrderPush, SyncTrigger::Checkout, 124);
    $failed->markFailed('SQLSTATE 23505');

    $all = $this->actingAs($this->admin)->getJson('/api/v1/admin/sync-logs')->assertOk();

    expect(collect($all->json('data'))->pluck('type')->all())->toBe(['order_push', 'products'])
        ->and($all->json('data.1'))->toMatchArray(['status' => 'success', 'items_processed' => 25, 'duration_seconds' => 72, 'reference_id' => null])
        ->and($all->json('data.0'))->toMatchArray(['status' => 'failed', 'reference_id' => 124, 'message' => 'SQLSTATE 23505'])
        ->and($all->json('meta.total'))->toBe(2);

    $byType = $this->actingAs($this->admin)->getJson('/api/v1/admin/sync-logs?type=products')->json('data');
    $byStatus = $this->actingAs($this->admin)->getJson('/api/v1/admin/sync-logs?status=failed')->json('data');
    expect($byType)->toHaveCount(1)->and($byStatus)->toHaveCount(1)->and($byStatus[0]['type'])->toBe('order_push');
});

test('ADM-14 rejects unknown filters', function (string $query, string $field) {
    assertApiError($this->actingAs($this->admin)->getJson("/api/v1/admin/sync-logs?{$query}"), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors($field);
})->with([['type=lain', 'type'], ['status=oke', 'status'], ['per_page=500', 'per_page']]);

test('sync-logs:prune deletes logs older than 90 days and keeps the rest', function () {
    $old = SyncLog::start(SyncType::Stock, SyncTrigger::Schedule);
    $old->forceFill(['started_at' => now()->subDays(91)])->save();
    $recent = SyncLog::start(SyncType::Stock, SyncTrigger::Schedule);
    $recent->forceFill(['started_at' => now()->subDays(89)])->save();

    $this->artisan('sync-logs:prune')->assertSuccessful();

    expect(SyncLog::pluck('id')->all())->toBe([$recent->id]);
});
