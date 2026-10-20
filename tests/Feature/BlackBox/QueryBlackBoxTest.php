<?php

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| Query audit: an endpoint must not issue more queries when it returns more rows (N+1), and stays inside a budget.
| Each endpoint is measured with a small and a large data set; the counts must match.
*/

function bbQueryCount(Closure $request): int
{
    Cache::flush();
    $count = 0;
    DB::listen(function ($query) use (&$count) {
        if (! str_starts_with(strtolower(ltrim($query->sql)), 'savepoint') && ! str_contains($query->sql, 'information_schema')) {
            $count++;
        }
    });
    $request();

    return $count;
}

function bbSeedCatalog(int $products): void
{
    $category = Category::factory()->create();
    foreach (range(1, $products) as $i) {
        $product = Product::factory()->create(['category_id' => $category->id]);
        ProductVariant::factory()->count(3)->for($product)->create();
    }
}

function bbSeedLogs(int $count): void
{
    foreach (range(1, $count) as $i) {
        SyncLog::start(SyncType::Products, SyncTrigger::Manual);
    }
}

function bbSeedOrders(User $user, int $orders): void
{
    foreach (range(1, $orders) as $i) {
        $order = Order::factory()->for($user)->create();
        foreach (range(1, 3) as $j) {
            $order->items()->create(['jubelio_item_id' => $j, 'sku' => "S{$i}{$j}", 'product_name' => 'Aki', 'unit' => 'Unit', 'price' => 1000, 'qty' => 1, 'weight_gram' => 100, 'subtotal' => 1000]);
        }
        Payment::create(['order_id' => $order->id, 'gapura_reference_no' => "R{$order->id}", 'partner_reference_no' => $order->order_number, 'checkout_url' => 'x', 'amount' => 1000, 'status' => 'PENDING', 'expires_at' => now()->addHour()]);
    }
}

test('GET /products issues the same number of queries for 2 and 24 products', function () {
    bbSeedCatalog(2);
    $small = bbQueryCount(fn () => $this->getJson('/api/v1/products?per_page=48')->assertOk());
    bbSeedCatalog(22);
    $large = bbQueryCount(fn () => $this->getJson('/api/v1/products?per_page=48')->assertOk());

    expect($large)->toBe($small)->and($large)->toBeLessThanOrEqual(8);
});

test('GET /products with every filter at once stays within the same budget', function () {
    bbSeedCatalog(10);
    $queries = bbQueryCount(fn () => $this->getJson('/api/v1/products?q=Sepeda&category=x&in_stock=1&price_min=1&price_max=99999999&sort=price_asc&per_page=48')->assertOk());

    expect($queries)->toBeLessThanOrEqual(10);
});

test('GET /products/{slug} does not query per variant or per image', function () {
    $few = Product::factory()->create(['slug' => 'few']);
    ProductVariant::factory()->count(1)->for($few)->create();
    $many = Product::factory()->create(['slug' => 'many']);
    ProductVariant::factory()->count(15)->for($many)->create();

    $a = bbQueryCount(fn () => $this->getJson('/api/v1/products/few')->assertOk());
    $b = bbQueryCount(fn () => $this->getJson('/api/v1/products/many')->assertOk());

    expect($b)->toBe($a)->and($b)->toBeLessThanOrEqual(6);
});

test('GET /categories issues a constant number of queries however many categories exist', function () {
    bbSeedCatalog(2);
    $small = bbQueryCount(fn () => $this->getJson('/api/v1/categories?with_empty=1')->assertOk());
    Category::factory()->count(30)->create();
    $large = bbQueryCount(fn () => $this->getJson('/api/v1/categories?with_empty=1')->assertOk());

    expect($large)->toBe($small);
});

test('GET /cart and /cart/count do not query per line', function () {
    $user = bbCustomer();
    $line = fn () => $user->cartItems()->create(['product_variant_id' => ProductVariant::factory()->for(Product::factory()->create())->create(['available_qty' => 9])->id, 'qty' => 1]);
    $line();
    $cart1 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/cart')->assertOk());
    $count1 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/cart/count')->assertOk());
    foreach (range(1, 14) as $i) {
        $line();
    }
    $cart2 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/cart')->assertOk());
    $count2 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/cart/count')->assertOk());

    expect($cart2)->toBe($cart1)->and($count2)->toBe($count1);
});

test('GET /addresses does not query per address', function () {
    $user = bbCustomer();
    Address::factory()->for($user)->create();
    $small = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/addresses')->assertOk());
    Address::factory()->count(8)->for($user)->create();
    $large = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/addresses')->assertOk());

    expect($large)->toBe($small);
});

test('GET /orders and /orders/{no} do not query per order or per item', function () {
    $user = bbCustomer();
    bbSeedOrders($user, 1);
    $list1 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/orders')->assertOk());
    bbSeedOrders($user, 9);
    $list2 = bbQueryCount(fn () => $this->actingAs($user)->getJson('/api/v1/orders')->assertOk());

    $number = Order::latest('id')->value('order_number');
    $detail = bbQueryCount(fn () => $this->actingAs($user)->getJson("/api/v1/orders/{$number}")->assertOk());

    expect($list2)->toBe($list1)->and($detail)->toBeLessThanOrEqual(8);
});

test('admin order list and sync logs do not query per row', function () {
    $admin = bbAdmin();
    $customer = bbCustomer();
    bbSeedOrders($customer, 1);
    bbSeedLogs(1);
    $orders1 = bbQueryCount(fn () => $this->actingAs($admin)->getJson('/api/v1/admin/orders?q=a')->assertOk());
    $logs1 = bbQueryCount(fn () => $this->actingAs($admin)->getJson('/api/v1/admin/sync-logs')->assertOk());
    foreach (range(1, 9) as $i) {
        bbSeedOrders(bbCustomer(), 1);
    }
    bbSeedLogs(9);
    $orders2 = bbQueryCount(fn () => $this->actingAs($admin)->getJson('/api/v1/admin/orders?q=a')->assertOk());
    $logs2 = bbQueryCount(fn () => $this->actingAs($admin)->getJson('/api/v1/admin/sync-logs')->assertOk());

    expect($orders2)->toBe($orders1)->and($logs2)->toBe($logs1);
});

test('every column the app filters or joins on is covered by an index (no full scans as data grows)', function () {
    // Leading column of any index, so composites such as (status, payment_expires_at) and (user_id, created_at) count.
    // Sort-only columns of the admin screens (orders.created_at, sync_logs.started_at) are deliberately not required:
    // the tables are small and pruned (sync-logs:prune), and a filesort there is cheaper than another index to maintain.
    $indexed = fn (string $table, string $column) => DB::table('information_schema.statistics')
        ->where('table_schema', DB::getDatabaseName())->where('table_name', $table)->where('column_name', $column)->where('seq_in_index', 1)->exists();

    $expected = [
        'orders' => ['user_id', 'status', 'order_number', 'jubelio_sync_status'],
        'order_items' => ['order_id'], 'payments' => ['order_id', 'gapura_reference_no'],
        'cart_items' => ['user_id', 'product_variant_id'], 'addresses' => ['user_id'],
        'products' => ['slug', 'category_id'], 'product_variants' => ['product_id', 'sku'], 'categories' => ['slug', 'parent_id'],
        'users' => ['email'], 'webhook_events' => ['idempotency_key'], 'sync_logs' => ['type'], 'order_status_histories' => ['order_id'],
    ];

    $missing = [];
    foreach ($expected as $table => $columns) {
        foreach ($columns as $column) {
            if (! $indexed($table, $column)) {
                $missing[] = "{$table}.{$column}";
            }
        }
    }

    expect($missing)->toBe([]);
});
