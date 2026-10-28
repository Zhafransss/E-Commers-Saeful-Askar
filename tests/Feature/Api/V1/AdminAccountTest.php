<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
| ADM-25 – ADM-28 (docs/api/admin.md): the store's own admin accounts.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Utama', 'email' => 'utama@example.com']);
});

it('lists only admins, flagging the logged-in one', function () {
    User::factory()->create(['name' => 'Pelanggan Biasa']);
    $other = User::factory()->admin()->create(['name' => 'Admin Kedua']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/admins')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Admin Utama', 'Admin Kedua']);
    $response->assertJsonPath('data.0.is_you', true)->assertJsonPath('data.1.is_you', false);
    expect($response->json('data.0'))->not->toHaveKeys(['password', 'remember_token']);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/admins?q=kedua')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
});

it('creates an admin who can sign in at once', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/admins', [
        'name' => 'Budi Santoso', 'email' => 'Budi@Example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ])->assertCreated()->assertJsonPath('data.email', 'budi@example.com')->assertJsonPath('data.is_you', false);

    $budi = User::where('email', 'budi@example.com')->firstOrFail();
    expect($budi->isAdmin())->toBeTrue()
        ->and($budi->email_verified_at)->not->toBeNull()
        ->and(Hash::check('rahasia123', $budi->password))->toBeTrue();
});

it('refuses an admin with a bad or taken email, or a weak password', function (array $input, string $field) {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/admins', $input + ['name' => 'Budi', 'email' => 'budi@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
        ->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'email taken by a customer' => [['email' => 'dipakai@example.com'], 'email'],
    'not an email' => [['email' => 'bukan-email'], 'email'],
    'short password' => [['password' => 'abc12', 'password_confirmation' => 'abc12'], 'password'],
    'no digit' => [['password' => 'hurufsaja', 'password_confirmation' => 'hurufsaja'], 'password'],
    'not confirmed' => [['password_confirmation' => 'lain12345'], 'password'],
    'no name' => [['name' => ''], 'name'],
]);

it('updates the name and email without touching the password', function () {
    $other = User::factory()->admin()->create(['email' => 'lama@example.com']);
    $hash = $other->password;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$other->id}", ['name' => 'Nama Baru', 'email' => 'baru@example.com'])
        ->assertOk()->assertJsonPath('data.name', 'Nama Baru');

    expect($other->fresh())->name->toBe('Nama Baru')->email->toBe('baru@example.com')->password->toBe($hash);
});

it('lets an admin keep their own email on update', function () {
    $this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$this->admin->id}", ['name' => 'Admin Utama 2', 'email' => 'utama@example.com'])->assertOk();
});

it('refuses an email that another account has', function () {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$this->admin->id}", ['email' => 'dipakai@example.com'])
        ->assertStatus(422)->assertJsonValidationErrors('email');
});

it('changes a password only when one is sent, and signs that admin out elsewhere', function () {
    $other = User::factory()->admin()->create();
    $token = $other->remember_token;

    $this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$other->id}", ['password' => '', 'password_confirmation' => ''])->assertOk();
    expect(Hash::check('password', $other->fresh()->password))->toBeTrue();

    $this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$other->id}", ['password' => 'barubaru1', 'password_confirmation' => 'barubaru1'])->assertOk();
    expect(Hash::check('barubaru1', $other->fresh()->password))->toBeTrue()
        ->and($other->fresh()->remember_token)->not->toBe($token);
});

it('answers 404 ADMIN_NOT_FOUND for a customer or an unknown id', function () {
    $customer = User::factory()->create();

    assertApiError($this->actingAs($this->admin)->putJson("/api/v1/admin/admins/{$customer->id}", ['name' => 'Ubah']), 404, 'ADMIN_NOT_FOUND');
    assertApiError($this->actingAs($this->admin)->deleteJson("/api/v1/admin/admins/{$customer->id}"), 404, 'ADMIN_NOT_FOUND');
    assertApiError($this->actingAs($this->admin)->deleteJson('/api/v1/admin/admins/999999'), 404, 'ADMIN_NOT_FOUND');
    expect($customer->fresh())->not->toBeNull();
});

it('deletes another admin', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/admins/{$other->id}")->assertOk();

    expect(User::find($other->id))->toBeNull();
});

it('does not let an admin delete their own account', function () {
    assertApiError($this->actingAs($this->admin)->deleteJson("/api/v1/admin/admins/{$this->admin->id}"), 409, 'CANNOT_DELETE_SELF');

    expect($this->admin->fresh())->not->toBeNull();
});

it('keeps customers and guests out', function () {
    $this->getJson('/api/v1/admin/admins')->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/admins')->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson('/api/v1/admin/admins', [])->assertForbidden();
});
