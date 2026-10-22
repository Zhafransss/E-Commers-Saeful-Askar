<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| ADM-18 – ADM-23 (docs/api/admin.md) and the admin-only rules of the shop: customers, products, shipments (resi),
| the bulk payment check, the invoice PDF, and an admin who cannot use the cart or checkout.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '081377788899']);
});

function panelOrder(User $user, string $number, array $attributes = [], bool $withPayment = true): Order
{
    $order = Order::factory()->for($user)->create($attributes + ['order_number' => $number, 'grand_total' => 3585000, 'payment_expires_at' => now()->addHour()]);
    $order->items()->create(['jubelio_item_id' => 1, 'sku' => 'BAT-48V', 'product_name' => 'Baterai Lithium 48V', 'unit' => 'Unit', 'price' => 3400000, 'qty' => 1, 'weight_gram' => 9500, 'subtotal' => 3400000]);
    $withPayment && Payment::create(['order_id' => $order->id, 'gapura_reference_no' => 'R'.$number, 'partner_reference_no' => $number, 'checkout_url' => 'https://pay.test',
        'amount' => 3585000, 'status' => PaymentStatus::Pending, 'expires_at' => now()->addHour()]);

    return $order;
}

// --- Access ----------------------------------------------------------------------------------

test('the new admin endpoints refuse guests and customers', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs($this->customer)->json($method, $uri), 403, 'FORBIDDEN');
})->with([
    ['GET', '/api/v1/admin/users'],
    ['GET', '/api/v1/admin/users/1'],
    ['GET', '/api/v1/admin/products'],
    ['GET', '/api/v1/admin/shipments'],
    ['POST', '/api/v1/admin/shipments/refresh'],
    ['POST', '/api/v1/admin/orders/refresh-payments'],
]);

test('the invoice page is for admins only', function () {
    panelOrder($this->customer, 'WEB-000006');

    $this->get('/admin/orders/WEB-000006/invoice')->assertRedirect('/login');
    $this->actingAs($this->customer)->get('/admin/orders/WEB-000006/invoice')->assertForbidden();
});

// --- ADM-18 / ADM-19 customers -----------------------------------------------------------------

test('ADM-18 lists customers only, newest first, with their order totals and no secrets', function () {
    $older = User::factory()->create(['name' => 'Budi', 'created_at' => now()->subDay()]);
    panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Completed]);
    panelOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Expired]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/users')->assertOk();

    expect(collect($response->json('data'))->pluck('email')->all())->toBe([$this->customer->email, $older->email])
        ->and($response->json('data.0'))->toMatchArray(['name' => 'Siti Aminah', 'orders_count' => 2, 'paid_orders_count' => 1, 'paid_total' => 3585000])
        ->and($response->getContent())->not->toContain('password')->not->toContain('remember_token')->not->toContain($this->admin->email);
});

test('ADM-18 searches by name, email, and phone', function () {
    User::factory()->create(['name' => 'Budi', 'email' => 'budi@example.com']);

    foreach (['siti', 'siti@example', '0813777'] as $term) {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/users?q='.$term)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Siti Aminah');
    }
});

test('ADM-19 shows one customer with addresses and recent orders, and 404 for an admin or unknown id', function () {
    panelOrder($this->customer, 'WEB-000001');
    Address::factory()->for($this->customer)->default()->create(['recipient_name' => 'Siti di rumah']);

    $this->actingAs($this->admin)->getJson("/api/v1/admin/users/{$this->customer->id}")->assertOk()
        ->assertJsonPath('data.name', 'Siti Aminah')
        ->assertJsonPath('data.addresses.0.recipient_name', 'Siti di rumah')
        ->assertJsonPath('data.recent_orders.0.order_number', 'WEB-000001');

    assertApiError($this->actingAs($this->admin)->getJson("/api/v1/admin/users/{$this->admin->id}"), 404, 'USER_NOT_FOUND');
    assertApiError($this->actingAs($this->admin)->getJson('/api/v1/admin/users/99999'), 404, 'USER_NOT_FOUND');
});

test('there is no way to change a customer from the admin side', function () {
    foreach (['put', 'patch', 'delete', 'post'] as $method) {
        $this->actingAs($this->admin)->{$method.'Json'}("/api/v1/admin/users/{$this->customer->id}", ['name' => 'X'])->assertStatus(405);
    }
});

// --- ADM-20 products ---------------------------------------------------------------------------

test('ADM-20 lists products with all their variants, inactive ones too, and filters', function () {
    $product = Product::factory()->for(Category::factory())->create(['name' => 'Sepeda Listrik Uwinfly C1', 'is_active' => true]);
    ProductVariant::factory()->for($product)->create(['sku' => 'UWF-HTM', 'price' => 6750000, 'available_qty' => 4, 'is_active' => true]);
    ProductVariant::factory()->for($product)->create(['sku' => 'UWF-PTH', 'price' => 6900000, 'available_qty' => -2, 'is_active' => false]);
    Product::factory()->create(['name' => 'Baterai 48V', 'is_active' => false]);

    $all = $this->actingAs($this->admin)->getJson('/api/v1/admin/products')->assertOk();
    $bike = collect($all->json('data'))->firstWhere('name', 'Sepeda Listrik Uwinfly C1');

    expect($all->json('meta.total'))->toBe(2)
        ->and($bike['variants'])->toHaveCount(2)
        ->and($bike['price_min'])->toBe(6750000)->and($bike['total_available_qty'])->toBe(4)
        ->and(collect($bike['variants'])->pluck('is_active')->all())->toBe([true, false]);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/products?status=inactive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Baterai 48V');
    $this->actingAs($this->admin)->getJson('/api/v1/admin/products?q=UWF-PTH')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Sepeda Listrik Uwinfly C1');
    $this->actingAs($this->admin)->getJson('/api/v1/admin/products?status=nope')->assertUnprocessable();
});

// --- ADM-21 / ADM-22 shipments -----------------------------------------------------------------

test('ADM-21 lists paid orders with their resi and filters by having one', function () {
    panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Shipped, 'tracking_no' => 'JT123', 'jubelio_courier' => 'JNE']);
    panelOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Paid]);
    panelOrder($this->customer, 'WEB-000003', ['status' => OrderStatus::PendingPayment]);

    $all = $this->actingAs($this->admin)->getJson('/api/v1/admin/shipments')->assertOk();

    expect(collect($all->json('data'))->pluck('order_number')->all())->toBe(['WEB-000002', 'WEB-000001'])
        ->and(collect($all->json('data'))->firstWhere('order_number', 'WEB-000001'))->toMatchArray(['tracking_no' => 'JT123', 'jubelio_courier' => 'JNE']);
    $this->actingAs($this->admin)->getJson('/api/v1/admin/shipments?resi=ada')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'WEB-000001');
    $this->actingAs($this->admin)->getJson('/api/v1/admin/shipments?resi=belum')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', 'WEB-000002');
    $this->actingAs($this->admin)->getJson('/api/v1/admin/shipments?q=JT123')->assertJsonCount(1, 'data');
});

function fakeJubelioSalesOrders(array $byId): void
{
    config(['jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'a@b.c', 'jubelio.password' => 'x']);
    Http::fake(function ($request) use ($byId) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/login') {
            return Http::response(fixture('jubelio/login.json'));
        }

        return preg_match('#/sales/orders/(\d+)$#', $path, $m) && isset($byId[(int) $m[1]]) ? Http::response($byId[(int) $m[1]]) : Http::response(['message' => 'Not Found'], 404);
    });
}

test('ADM-22 takes the resi from Jubelio and keeps it on the order', function () {
    $found = panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 11]);
    $waiting = panelOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 12]);
    fakeJubelioSalesOrders([11 => ['salesorder_id' => 11, 'tracking_no' => ' JP123456 ', 'shipper' => 'JNE Reguler'], 12 => ['salesorder_id' => 12, 'tracking_no' => null]]);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/shipments/refresh')->assertOk();

    expect($response->json('data'))->toBe(['checked' => 2, 'updated' => 1, 'failed' => 0, 'remaining' => 0])
        ->and($response->json('message'))->toBe('1 resi baru ditemukan di Jubelio.')
        ->and($found->fresh())->tracking_no->toBe('JP123456')->jubelio_courier->toBe('JNE Reguler')
        ->and($waiting->fresh()->tracking_no)->toBeNull();
});

test('ADM-22 never types a resi: orders without a Jubelio sales order, unpaid ones, and ones that have a resi are not asked', function () {
    panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => null]);
    panelOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::PendingPayment, 'jubelio_salesorder_id' => 12]);
    panelOrder($this->customer, 'WEB-000003', ['status' => OrderStatus::Shipped, 'jubelio_salesorder_id' => 13, 'tracking_no' => 'KEEP']);
    fakeJubelioSalesOrders([]);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/shipments/refresh')->assertOk()
        ->assertJsonPath('data.checked', 0)->assertJsonPath('message', 'Tidak ada pesanan yang menunggu resi.');
    expect(Order::where('order_number', 'WEB-000003')->value('tracking_no'))->toBe('KEEP');
});

test('ADM-22 survives Jubelio failing for one order and asks each order in turn', function () {
    $good = panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 11]);
    panelOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 99]);
    fakeJubelioSalesOrders([11 => ['salesorder_id' => 11, 'tracking_no' => 'AB1']]);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/shipments/refresh')->assertOk();

    expect($response->json('data.updated'))->toBe(1)->and($good->fresh()->tracking_no)->toBe('AB1');
});

// --- ADM-23 bulk payment check -----------------------------------------------------------------

function danaAnswersFor(array $byNumber): void
{
    config(['gapura.env' => 'sandbox', 'gapura.client_id' => 'c', 'gapura.merchant_id' => 'm', 'gapura.mcc' => '5940',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.origin' => 'https://toko.test']);
    $responses = [];

    foreach ($byNumber as $number => $code) {
        $body = ['responseCode' => '2005500', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => $number, 'originalReferenceNo' => 'R',
            'serviceCode' => '54', 'latestTransactionStatus' => $code, 'transactionStatusDesc' => 'x', 'amount' => ['value' => '3585000.00', 'currency' => 'IDR']];
        $code === '00' && $body['paidTime'] = '2026-10-20T10:00:00+07:00';
        $responses[] = new Response(200, [], json_encode($body));
    }

    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler($responses))])));
}

test('ADM-23 asks DANA about every waiting order and updates the ones that changed', function () {
    Queue::fake();
    Mail::fake();
    panelOrder($this->customer, 'WEB-000001', ['created_at' => now()->subMinutes(3)]);
    panelOrder($this->customer, 'WEB-000002', ['created_at' => now()->subMinutes(2)]);
    panelOrder($this->customer, 'WEB-000003', ['created_at' => now()->subMinute()]);
    panelOrder($this->customer, 'WEB-000004', ['status' => OrderStatus::Completed]);
    // Newest first: 000003, 000002, 000001.
    danaAnswersFor(['WEB-000003' => '00', 'WEB-000002' => '01', 'WEB-000001' => '05']);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/orders/refresh-payments')->assertOk();

    expect($response->json('data'))->toBe(['checked' => 3, 'paid' => 1, 'expired' => 1, 'failed' => 0, 'remaining' => 0])
        ->and($response->json('message'))->toBe('3 pesanan diperiksa: 1 dibayar, 1 kedaluwarsa.')
        ->and(Order::where('order_number', 'WEB-000003')->value('status'))->toBe(OrderStatus::Paid)
        ->and(Order::where('order_number', 'WEB-000002')->value('status'))->toBe(OrderStatus::PendingPayment)
        ->and(Order::where('order_number', 'WEB-000001')->value('status'))->toBe(OrderStatus::Expired)
        ->and(Order::where('order_number', 'WEB-000004')->value('status'))->toBe(OrderStatus::Completed);
});

test('ADM-23 says so when nothing waits, and keeps going when DANA fails for one order', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/orders/refresh-payments')->assertOk()
        ->assertJsonPath('data.checked', 0)->assertJsonPath('message', 'Tidak ada pesanan yang menunggu pembayaran.');

    panelOrder($this->customer, 'WEB-000001');
    config(['gapura.env' => 'sandbox', 'gapura.client_id' => 'c', 'gapura.merchant_id' => 'm', 'gapura.mcc' => '5940', 'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.origin' => 'https://toko.test']);
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(500, [], '{}'), new Response(500, [], '{}')]))])));

    $this->actingAs($this->admin)->postJson('/api/v1/admin/orders/refresh-payments')->assertOk()
        ->assertJsonPath('data.failed', 1)->assertJsonPath('message', 'DANA tidak bisa dihubungi. Coba lagi beberapa saat.');
});

// --- Invoice ---------------------------------------------------------------------------------

test('the invoice of an order is a PDF the admin can open or download', function () {
    $order = panelOrder($this->customer, 'WEB-000006', ['status' => OrderStatus::Paid, 'paid_at' => now()]);

    $open = $this->actingAs($this->admin)->get('/admin/orders/WEB-000006/invoice')->assertOk();
    $download = $this->actingAs($this->admin)->get('/admin/orders/WEB-000006/invoice?download=1')->assertOk();

    expect($open->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($open->headers->get('Content-Disposition'))->toBe('inline; filename="inv-web-000006.pdf"')
        ->and($download->headers->get('Content-Disposition'))->toBe('attachment; filename="inv-web-000006.pdf"')
        ->and(substr($open->getContent(), 0, 5))->toBe('%PDF-')
        ->and(strlen($open->getContent()))->toBeGreaterThan(2000);

    $this->actingAs($this->admin)->get('/admin/orders/WEB-999999/invoice')->assertNotFound();
});

test('the invoice says LUNAS only for a paid order', function () {
    $html = fn (Order $order) => view('admin.invoice', [
        'order' => $order->load(['user', 'items', 'payment']), 'number' => 'INV-'.$order->order_number,
        'store' => require resource_path('static/store.php'), 'paid' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed], true),
    ])->render();

    expect($html(panelOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid])))->toContain('LUNAS')->toContain('Baterai Lithium 48V')->toContain('Rp3.585.000')
        ->and($html(panelOrder($this->customer, 'WEB-000002')))->toContain('MENUNGGU PEMBAYARAN')->not->toContain('LUNAS');
});

// --- An admin does not shop ------------------------------------------------------------------

test('an admin cannot use the cart, shipping rates, or checkout', function (string $method, string $uri) {
    assertApiError($this->actingAs($this->admin)->json($method, $uri, ['address_id' => 1]), 403, 'ADMIN_CANNOT_SHOP');
})->with([
    ['GET', '/api/v1/cart'],
    ['POST', '/api/v1/cart/items'],
    ['PATCH', '/api/v1/cart/items/1'],
    ['DELETE', '/api/v1/cart/items/1'],
    ['DELETE', '/api/v1/cart'],
    ['POST', '/api/v1/shipping/rates'],
    ['POST', '/api/v1/checkout'],
]);

test('the cart badge still answers an admin with zero', function () {
    $this->actingAs($this->admin)->getJson('/api/v1/cart/count')->assertOk()->assertJsonPath('data.total_qty', 0);
});

test('an admin is sent back to the panel from the shop pages and sees no cart in the header', function (string $uri) {
    $this->actingAs($this->admin)->get($uri)->assertRedirect('/admin/orders');
})->with(['/cart', '/checkout', '/account/orders']);

test('the storefront header shows the cart to customers and guests but not to an admin', function () {
    $this->get('/')->assertOk()->assertSee('aria-label="Keranjang"', false);
    $this->actingAs($this->customer)->get('/')->assertSee('aria-label="Keranjang"', false);
    $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee('aria-label="Keranjang"', false);
});
