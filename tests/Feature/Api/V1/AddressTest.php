<?php

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
| ADDR-01 – ADDR-06 (docs/api/addresses-regions.md). Region names come from
| Jubelio's lists (fakeJubelioRegions), never from the request.
*/

beforeEach(function () {
    fakeJubelioRegions();
    $this->user = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function addressBody(array $overrides = []): array
{
    return $overrides + [
        'label' => 'Rumah',
        'recipient_name' => 'Budi Santoso',
        'phone' => '081234567890',
        'address_line' => 'Jl. Melati No. 12, RT 03/RW 05',
        'province_id' => '32',
        'city_id' => '3216',
        'district_id' => '321605',
        'subdistrict_id' => '3216052001',
        'post_code' => '17565',
    ];
}

function addressOf(User $user, array $attributes = []): Address
{
    return Address::factory()->for($user)->create($attributes);
}

test('addresses and regions require login', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
})->with([
    ['GET', '/api/v1/addresses'], ['POST', '/api/v1/addresses'], ['GET', '/api/v1/addresses/1'],
    ['PUT', '/api/v1/addresses/1'], ['DELETE', '/api/v1/addresses/1'], ['PATCH', '/api/v1/addresses/1/default'],
    ['GET', '/api/v1/regions/provinces'], ['GET', '/api/v1/regions/cities?province_id=32'],
]);

// --- ADDR-02 -------------------------------------------------------------------

test('ADDR-02 stores the address with region names from Jubelio and answers 201', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody([
        'province_name' => 'PALSU', 'phone' => '+62 812-3456-7890',
    ]))->assertCreated()->assertJsonPath('message', 'Alamat disimpan.');

    expect($response->json('data'))->toMatchArray([
        'label' => 'Rumah',
        'recipient_name' => 'Budi Santoso',
        'phone' => '081234567890',
        'address_line' => 'Jl. Melati No. 12, RT 03/RW 05',
        'province' => ['id' => '32', 'name' => 'JAWA BARAT'],
        'city' => ['id' => '3216', 'name' => 'KAB. BEKASI'],
        'district' => ['id' => '321605', 'name' => 'Tambun Utara'],
        'subdistrict' => ['id' => '3216052001', 'name' => 'Satriajaya'],
        'post_code' => '17565',
        'is_default' => true,
    ])->and(array_keys($response->json('data')))->toBe([
        'id', 'label', 'recipient_name', 'phone', 'address_line', 'province', 'city', 'district', 'subdistrict',
        'post_code', 'is_default', 'created_at', 'updated_at',
    ]);
});

test('ADDR-02 makes the first address the default even without is_default', function () {
    $this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody(['is_default' => false]))->assertJsonPath('data.is_default', true);
    $this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody(['label' => 'Kantor']))->assertJsonPath('data.is_default', false);
});

test('ADDR-02 with is_default moves the default in the same request', function () {
    $old = addressOf($this->user, ['is_default' => true]);

    $new = $this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody(['is_default' => true]))->json('data.id');

    expect($old->fresh()->is_default)->toBeFalse()
        ->and($this->user->addresses()->where('is_default', true)->pluck('id')->all())->toBe([$new]);
});

test('ADDR-02 checks the region chain and answers REGION_NOT_FOUND on the wrong field', function (array $ids, string $field, string $message) {
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody($ids)), 422, 'REGION_NOT_FOUND')
        ->assertJsonPath('message', $message)
        ->assertJsonValidationErrors($field);

    expect(Address::count())->toBe(0);
})->with([
    'provinsi tak dikenal' => [['province_id' => '99'], 'province_id', 'Provinsi yang dipilih tidak ditemukan.'],
    'kota bukan di provinsi' => [['province_id' => '31'], 'city_id', 'Kota yang dipilih tidak berada di provinsi tersebut.'],
    'kecamatan bukan di kota' => [['district_id' => '317101'], 'district_id', 'Kecamatan yang dipilih tidak berada di kota tersebut.'],
    'kelurahan bukan di kecamatan' => [['subdistrict_id' => '3216122004'], 'subdistrict_id', 'Kelurahan yang dipilih tidak berada di kecamatan tersebut.'],
]);

test('ADDR-02 refuses the 11th address', function () {
    Address::factory()->for($this->user)->count(10)->create();

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody()), 422, 'ADDRESS_LIMIT_REACHED');
    expect($this->user->addresses()->count())->toBe(10)
        ->and(jubelioCalls())->toBeEmpty();
});

test('ADDR-02 validates the fields', function (array $overrides, string $field) {
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody($overrides)), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
})->with([
    'nama penerima kosong' => [['recipient_name' => ''], 'recipient_name'],
    'HP salah' => [['phone' => '021555'], 'phone'],
    'alamat terlalu pendek' => [['address_line' => 'Jl. A'], 'address_line'],
    'kode pos 4 digit' => [['post_code' => '1756'], 'post_code'],
    'kode pos huruf' => [['post_code' => '17A65'], 'post_code'],
    'tanpa provinsi' => [['province_id' => null], 'province_id'],
    'label terlalu panjang' => [['label' => str_repeat('a', 51)], 'label'],
]);

test('ADDR-02 when Jubelio regions are down answers 502 and saves nothing', function () {
    fakeJubelioRegions(['/region/provinces' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody()), 502, 'INTEGRATION_ERROR');
    expect(Address::count())->toBe(0);
});

// --- ADDR-01 / ADDR-03 ---------------------------------------------------------

test('ADDR-01 lists only my addresses, default first, then newest', function () {
    $old = addressOf($this->user, ['created_at' => now()->subDays(3)]);
    $default = addressOf($this->user, ['is_default' => true, 'created_at' => now()->subDays(5)]);
    $newest = addressOf($this->user, ['created_at' => now()->subDay()]);
    addressOf(User::factory()->create());

    expect(collect($this->actingAs($this->user)->getJson('/api/v1/addresses')->assertOk()->json('data'))->pluck('id')->all())
        ->toBe([$default->id, $newest->id, $old->id]);
});

test('ADDR-01 without addresses answers an empty list', function () {
    $this->actingAs($this->user)->getJson('/api/v1/addresses')->assertExactJson(['data' => []]);
});

test('ADDR-03 shows my address and hides other users\' as 404 ADDRESS_NOT_FOUND', function () {
    $mine = addressOf($this->user);
    $theirs = addressOf(User::factory()->create());

    $this->actingAs($this->user)->getJson("/api/v1/addresses/{$mine->id}")->assertOk()->assertJsonPath('data.id', $mine->id);
    assertApiError($this->actingAs($this->user)->getJson("/api/v1/addresses/{$theirs->id}"), 404, 'ADDRESS_NOT_FOUND')
        ->assertExactJson(['message' => 'Alamat tidak ditemukan.', 'code' => 'ADDRESS_NOT_FOUND']);
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/addresses/999999'), 404, 'ADDRESS_NOT_FOUND');
});

// --- ADDR-04 -------------------------------------------------------------------

test('ADDR-04 updates the address with the same checks', function () {
    $address = addressOf($this->user, ['is_default' => true]);

    $this->actingAs($this->user)->putJson("/api/v1/addresses/{$address->id}", addressBody(['address_line' => 'Jl. Melati No. 14, dekat masjid', 'subdistrict_id' => '3216052006']))
        ->assertOk()
        ->assertJsonPath('message', 'Alamat diperbarui.')
        ->assertJsonPath('data.address_line', 'Jl. Melati No. 14, dekat masjid')
        ->assertJsonPath('data.subdistrict', ['id' => '3216052006', 'name' => 'Srijaya']);

    assertApiError($this->actingAs($this->user)->putJson("/api/v1/addresses/{$address->id}", addressBody(['city_id' => '3275'])), 422, 'REGION_NOT_FOUND');
});

test('ADDR-04 is_default = false on the only default is ignored', function () {
    $address = addressOf($this->user, ['is_default' => true]);

    $this->actingAs($this->user)->putJson("/api/v1/addresses/{$address->id}", addressBody(['is_default' => false]))
        ->assertOk()
        ->assertJsonPath('data.is_default', true);
});

test('ADDR-04 with is_default = true moves the default', function () {
    $default = addressOf($this->user, ['is_default' => true]);
    $other = addressOf($this->user);

    $this->actingAs($this->user)->putJson("/api/v1/addresses/{$other->id}", addressBody(['is_default' => true]))->assertJsonPath('data.is_default', true);

    expect($default->fresh()->is_default)->toBeFalse();
});

test('ADDR-04 and ADDR-05 on another user\'s address answer 404 and change nothing', function (string $method) {
    $theirs = addressOf(User::factory()->create(), ['address_line' => 'Alamat orang lain di sini']);

    assertApiError($this->actingAs($this->user)->json($method, "/api/v1/addresses/{$theirs->id}", addressBody()), 404, 'ADDRESS_NOT_FOUND');
    expect($theirs->fresh())->not->toBeNull()->address_line->toBe('Alamat orang lain di sini');
})->with(['PUT', 'DELETE']);

// --- ADDR-05 -------------------------------------------------------------------

test('ADDR-05 deleting the default makes the newest remaining address the default', function () {
    $default = addressOf($this->user, ['is_default' => true]);
    $older = addressOf($this->user, ['created_at' => now()->subDays(5)]);
    $newer = addressOf($this->user, ['created_at' => now()->subDay()]);

    $this->actingAs($this->user)->deleteJson("/api/v1/addresses/{$default->id}")->assertOk()->assertExactJson(['message' => 'Alamat dihapus.']);

    expect($newer->fresh()->is_default)->toBeTrue()
        ->and($older->fresh()->is_default)->toBeFalse()
        ->and(Address::find($default->id))->toBeNull();
});

test('ADDR-05 deleting the last address leaves none', function () {
    $only = addressOf($this->user, ['is_default' => true]);

    $this->actingAs($this->user)->deleteJson("/api/v1/addresses/{$only->id}")->assertOk();

    expect($this->user->addresses()->count())->toBe(0);
});

// --- ADDR-06 -------------------------------------------------------------------

test('ADDR-06 makes exactly one default and answers the list', function () {
    $a = addressOf($this->user, ['is_default' => true]);
    $b = addressOf($this->user);
    $theirs = addressOf(User::factory()->create(), ['is_default' => true]);

    $response = $this->actingAs($this->user)->patchJson("/api/v1/addresses/{$b->id}/default")
        ->assertOk()
        ->assertJsonPath('message', 'Alamat utama diperbarui.')
        ->assertJsonPath('data.0.id', $b->id)
        ->assertJsonPath('data.0.is_default', true);

    expect($response->json('data'))->toHaveCount(2)
        ->and($this->user->addresses()->where('is_default', true)->pluck('id')->all())->toBe([$b->id])
        ->and($theirs->fresh()->is_default)->toBeTrue();

    assertApiError($this->actingAs($this->user)->patchJson("/api/v1/addresses/{$theirs->id}/default"), 404, 'ADDRESS_NOT_FOUND');
});

test('AUTH-05 has_default_address follows the address book', function () {
    $this->actingAs($this->user)->postJson('/api/v1/addresses', addressBody())->assertCreated();

    $this->actingAs($this->user)->getJson('/api/v1/auth/me')->assertJsonPath('data.has_default_address', true);
});
