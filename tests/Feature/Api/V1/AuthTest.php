<?php

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
| AUTH-02 – AUTH-09 (docs/api/auth.md). Session cookie auth through Sanctum's
| stateful API; fromFrontend() sends the Referer a browser would.
*/

const USER_KEYS = ['id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'has_default_address', 'created_at'];

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'phone' => '081234567890',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ];
}

function budi(string $password = 'rahasia123'): User
{
    return User::factory()->create(['email' => 'budi@example.com', 'password' => $password]);
}

// --- AUTH-02 Registrasi ----------------------------------------------------------

test('AUTH-02 registers a customer, logs them in, and answers the User object', function () {
    Http::fake();

    $response = fromFrontend()->postJson('/api/v1/auth/register', registration())
        ->assertCreated()
        ->assertJsonPath('data.email', 'budi@example.com')
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.has_default_address', false)
        ->assertJsonPath('data.email_verified_at', null);

    expect(array_keys($response->json('data')))->toBe(USER_KEYS);

    $user = User::sole();
    expect($user)
        ->role->toBe(UserRole::Customer)
        ->jubelio_contact_id->toBeNull()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();

    fromFrontend()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    Http::assertNothingSent(); // no Jubelio contact at registration
});

test('AUTH-02 stores the email in lower case and rejects the same email in other case', function () {
    fromFrontend()->postJson('/api/v1/auth/register', registration(['email' => 'Budi@Example.COM']))->assertCreated();
    expect(User::sole()->email)->toBe('budi@example.com');

    auth()->guard('web')->logout();
    assertApiError(fromFrontend()->postJson('/api/v1/auth/register', registration(['email' => 'BUDI@example.com'])), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('errors.email.0', 'Email sudah terdaftar.');
});

test('AUTH-02 refuses a phone number another account already has, whichever way it is written', function (string $second) {
    User::factory()->create(['phone' => '081234567890']);

    assertApiError(fromFrontend()->postJson('/api/v1/auth/register', registration(['phone' => $second, 'email' => 'baru@example.com'])), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('errors.phone.0', 'Nomor HP sudah terdaftar di akun lain.');
    expect(User::where('email', 'baru@example.com')->exists())->toBeFalse();
})->with(['081234567890', '6281234567890', '+62 812-3456-7890']);

test('AUTH-02 still says an invalid number is invalid, not taken', function () {
    User::factory()->create(['phone' => '081234567890']);

    assertApiError(fromFrontend()->postJson('/api/v1/auth/register', registration(['phone' => '12345', 'email' => 'baru@example.com'])), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('errors.phone', ['Nomor HP tidak valid.']);
});

test('AUTH-06 lets a customer keep their own number but not take another account\'s', function () {
    $other = User::factory()->create(['phone' => '081200000001']);
    $me = User::factory()->create(['phone' => '081200000002']);

    fromFrontend()->actingAs($me)->putJson('/api/v1/auth/profile', ['name' => 'Nama Baru', 'phone' => '081200000002'])->assertOk();
    assertApiError(fromFrontend()->actingAs($me)->putJson('/api/v1/auth/profile', ['name' => 'Nama Baru', 'phone' => '6281200000001']), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('errors.phone.0', 'Nomor HP sudah terdaftar di akun lain.');
    fromFrontend()->actingAs($me)->putJson('/api/v1/auth/profile', ['name' => 'Nama Baru', 'phone' => '081299999999'])->assertOk();

    expect($me->fresh()->phone)->toBe('081299999999')->and($other->fresh()->phone)->toBe('081200000001');
});

test('AUTH-02 normalizes 628… and separators to 08…', function (string $input, string $stored) {
    fromFrontend()->postJson('/api/v1/auth/register', registration(['phone' => $input]))->assertCreated();

    expect(User::sole()->phone)->toBe($stored);
})->with([
    ['6281234567890', '081234567890'],
    ['+62 812-3456-7890', '081234567890'],
    ['0812 3456 789', '08123456789'],
]);

test('AUTH-02 rejects invalid fields with Indonesian messages', function (array $overrides, string $field, ?string $message) {
    $response = assertApiError(fromFrontend()->postJson('/api/v1/auth/register', registration($overrides)), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);

    if ($message !== null) {
        $response->assertJsonPath("errors.{$field}.0", $message);
    }
})->with([
    'nama 1 huruf' => [['name' => 'B'], 'name', null],
    'email salah' => [['email' => 'bukan-email'], 'email', null],
    'HP bukan Indonesia' => [['phone' => '021123456'], 'phone', 'Nomor HP tidak valid.'],
    'HP terlalu pendek' => [['phone' => '0812345'], 'phone', 'Nomor HP tidak valid.'],
    'HP terlalu panjang' => [['phone' => '0812345678901234'], 'phone', 'Nomor HP tidak valid.'],
    'password tanpa angka' => [['password' => 'rahasiasaja', 'password_confirmation' => 'rahasiasaja'], 'password', 'Password minimal 8 karakter dan mengandung huruf serta angka.'],
    'password tanpa huruf' => [['password' => '12345678', 'password_confirmation' => '12345678'], 'password', 'Password minimal 8 karakter dan mengandung huruf serta angka.'],
    'password pendek' => [['password' => 'abc123', 'password_confirmation' => 'abc123'], 'password', 'Password minimal 8 karakter dan mengandung huruf serta angka.'],
    'konfirmasi beda' => [['password_confirmation' => 'lain12345'], 'password', null],
]);

test('AUTH-02 ignores a role sent by the client', function () {
    fromFrontend()->postJson('/api/v1/auth/register', registration(['role' => 'admin']))->assertCreated();

    expect(User::sole()->role)->toBe(UserRole::Customer);
});

// --- AUTH-03 Login ---------------------------------------------------------------

test('AUTH-03 logs in and answers the User object with role', function () {
    $user = budi();
    Address::factory()->for($user)->default()->create();

    fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'BUDI@example.com', 'password' => 'rahasia123'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.has_default_address', true);

    $this->assertAuthenticatedAs($user, 'web');
});

test('AUTH-03 gives the same error for an unknown email and a wrong password', function (string $email, string $password) {
    budi();

    assertApiError(fromFrontend()->postJson('/api/v1/auth/login', compact('email', 'password')), 422, 'INVALID_CREDENTIALS')
        ->assertExactJson(['message' => 'Email atau password salah.', 'code' => 'INVALID_CREDENTIALS']);

    $this->assertGuest('web');
})->with([
    'email tidak terdaftar' => ['tidak-ada@example.com', 'rahasia123'],
    'password salah' => ['budi@example.com', 'salah1234'],
]);

test('AUTH-03 regenerates the session id', function () {
    budi();
    $cookie = config('session.cookie');
    $before = str_repeat('a', 40);

    $response = fromFrontend()->withCookie($cookie, $before)
        ->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
        ->assertOk();

    expect($response->getCookie($cookie)->getValue())->not->toBe($before)->toHaveLength(40);
});

test('AUTH-03 with remember sets a remember-me cookie that lasts 30 days', function () {
    budi();

    $response = fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123', 'remember' => true])->assertOk();

    $recaller = $response->getCookie(auth()->guard('web')->getRecallerName());
    expect($recaller)->not->toBeNull()
        ->and($recaller->getExpiresTime())->toBeBetween(now()->addDays(30)->subMinute()->timestamp, now()->addDays(30)->addMinute()->timestamp);
});

test('AUTH-03 without email or password answers VALIDATION_ERROR', function () {
    assertApiError(fromFrontend()->postJson('/api/v1/auth/login', []), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors(['email', 'password']);
});

test('AUTH-03 locks after 5 attempts a minute with Retry-After', function () {
    budi();

    foreach (range(1, 5) as $attempt) {
        fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'salah1234']);
    }

    assertApiError(fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123']), 429, 'TOO_MANY_REQUESTS')
        ->assertHeader('Retry-After');
});

test('the CSRF check applies to the stateful API outside tests', function () {
    // Laravel skips CSRF while running tests; this asserts the middleware is in place.
    $middleware = app(Kernel::class)->getMiddlewareGroups()['api'];

    expect(collect($middleware)->contains(fn ($m) => str_contains($m, 'EnsureFrontendRequestsAreStateful')))->toBeTrue();
});

// --- AUTH-04 Logout --------------------------------------------------------------

test('AUTH-04 logs out, then /auth/me answers 401, and the cart stays', function () {
    $user = budi();
    $user->cartItems()->create(['product_variant_id' => ProductVariant::factory()->create()->id, 'qty' => 1]);
    fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertOk();

    fromFrontend()->postJson('/api/v1/auth/logout')->assertOk()->assertExactJson(['message' => 'Berhasil keluar.']);

    $this->assertGuest('web');
    app('auth')->forgetGuards(); // a new request starts with no cached user, as in production
    assertApiError(fromFrontend()->getJson('/api/v1/auth/me'), 401, 'UNAUTHENTICATED');
    expect($user->cartItems()->count())->toBe(1);
});

test('AUTH-04 for a guest answers 401', function () {
    assertApiError(fromFrontend()->postJson('/api/v1/auth/logout'), 401, 'UNAUTHENTICATED');
});

// --- AUTH-05 Me ------------------------------------------------------------------

test('AUTH-02, AUTH-03, and AUTH-06 answer the User object with a message; AUTH-05 without one', function () {
    $register = fromFrontend()->postJson('/api/v1/auth/register', registration())->assertCreated();
    expect(array_keys($register->json()))->toBe(['data', 'message'])
        ->and($register->json('message'))->toBe('Pendaftaran berhasil.');

    fromFrontend()->putJson('/api/v1/auth/profile', ['name' => 'Budi S.', 'phone' => '081298765432'])
        ->assertOk()->assertJsonPath('message', 'Profil diperbarui.')->assertJsonPath('data.name', 'Budi S.');

    expect(array_keys(fromFrontend()->getJson('/api/v1/auth/me')->assertOk()->json()))->toBe(['data']);

    fromFrontend()->postJson('/api/v1/auth/logout')->assertOk();
    app('auth')->forgetGuards();
    fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
        ->assertOk()->assertJsonPath('message', 'Login berhasil.')->assertJsonPath('data.email', 'budi@example.com');
});

test('AUTH-05 never exposes password, remember_token, or jubelio_contact_id', function () {
    $user = User::factory()->create();
    $user->forceFill(['jubelio_contact_id' => 555])->save();

    $data = $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk()->json('data');

    expect(array_keys($data))->toBe(USER_KEYS)
        ->and($data)->not->toHaveKeys(['password', 'remember_token', 'jubelio_contact_id'])
        ->and($data['created_at'])->toMatch('/\+07:00$/');
});

test('AUTH-05 has_default_address follows the addresses table', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertJsonPath('data.has_default_address', false);

    Address::factory()->for($user)->default()->create();
    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertJsonPath('data.has_default_address', true);
});

test('AUTH-05 shows the admin role so the frontend can show the admin menu', function () {
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/auth/me')->assertJsonPath('data.role', 'admin');
});

// --- AUTH-06 Profile -------------------------------------------------------------

test('AUTH-06 updates name and phone but never the email', function () {
    $user = budi();

    $this->actingAs($user)->putJson('/api/v1/auth/profile', ['name' => 'Budi S.', 'phone' => '6281298765432', 'email' => 'lain@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Budi S.')
        ->assertJsonPath('data.phone', '081298765432')
        ->assertJsonPath('data.email', 'budi@example.com');

    expect($user->fresh())->email->toBe('budi@example.com')->phone->toBe('081298765432');
});

test('AUTH-06 rejects an invalid phone', function () {
    assertApiError($this->actingAs(budi())->putJson('/api/v1/auth/profile', ['name' => 'Budi', 'phone' => '12345']), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('errors.phone.0', 'Nomor HP tidak valid.');
});

// --- AUTH-07 Password ------------------------------------------------------------

test('AUTH-07 changes the password; the next login uses the new one', function () {
    $user = budi();

    $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'rahasia123', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456',
    ])->assertOk()->assertExactJson(['message' => 'Password berhasil diubah.']);

    expect(Hash::check('rahasiaBaru456', $user->fresh()->password))->toBeTrue();
});

test('AUTH-07 with a wrong current password answers INVALID_CURRENT_PASSWORD and changes nothing', function () {
    $user = budi();

    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'salah1234', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456',
    ]), 422, 'INVALID_CURRENT_PASSWORD')->assertJsonPath('message', 'Password saat ini salah.');

    expect(Hash::check('rahasia123', $user->fresh()->password))->toBeTrue();
});

test('AUTH-07 rejects a weak, unconfirmed, or unchanged new password', function (array $body, string $field) {
    assertApiError($this->actingAs(budi())->putJson('/api/v1/auth/password', $body + ['current_password' => 'rahasia123']), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
})->with([
    'lemah' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], 'password'],
    'konfirmasi beda' => [['password' => 'rahasiaBaru456', 'password_confirmation' => 'lain'], 'password'],
    'sama dengan lama' => [['password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], 'password'],
]);

test('AUTH-07 signs out the other devices but keeps this one', function () {
    config(['session.driver' => 'database']);
    $user = budi();
    $oldRemember = $user->remember_token;
    DB::table('sessions')->insert([
        ['id' => 'perangkat-lain', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'user-lain', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $login = fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertOk();
    $current = $login->getCookie(config('session.cookie'))->getValue();

    fromFrontend()->withCookie(config('session.cookie'), $current)->putJson('/api/v1/auth/password', [
        'current_password' => 'rahasia123', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456',
    ])->assertOk();

    expect(DB::table('sessions')->pluck('id')->all())->toEqualCanonicalizing([$current, 'user-lain'])
        ->and($user->fresh()->remember_token)->not->toBe($oldRemember);
});

// --- AUTH-08 Forgot password -----------------------------------------------------

test('AUTH-08 sends a queued reset email with a link to /reset-password', function () {
    Notification::fake();
    $user = budi();

    fromFrontend()->postJson('/api/v1/auth/forgot-password', ['email' => 'Budi@example.com'])
        ->assertOk()
        ->assertExactJson(['message' => 'Jika email terdaftar, tautan reset password sudah dikirim.']);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $notification instanceof ShouldQueue
            && str_starts_with($mail->actionUrl, url('/reset-password').'?token=')
            && str_contains($mail->actionUrl, 'email=budi%40example.com')
            && collect($mail->outroLines)->contains(fn ($line) => str_contains($line, '60 menit'));
    });
});

test('AUTH-08 answers the same for an unknown email and sends nothing', function () {
    Notification::fake();

    fromFrontend()->postJson('/api/v1/auth/forgot-password', ['email' => 'tidak-ada@example.com'])
        ->assertOk()
        ->assertExactJson(['message' => 'Jika email terdaftar, tautan reset password sudah dikirim.']);

    Notification::assertNothingSent();
});

test('AUTH-08 answers the same when asked again right away (broker throttle)', function () {
    Notification::fake();
    budi();

    fromFrontend()->postJson('/api/v1/auth/forgot-password', ['email' => 'budi@example.com'])->assertOk();
    fromFrontend()->postJson('/api/v1/auth/forgot-password', ['email' => 'budi@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'Jika email terdaftar, tautan reset password sudah dikirim.');

    Notification::assertCount(1);
});

test('AUTH-08 validates the email', function () {
    assertApiError(fromFrontend()->postJson('/api/v1/auth/forgot-password', ['email' => 'bukan']), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('email');
});

// --- AUTH-09 Reset password ------------------------------------------------------

test('AUTH-09 sets the new password, ends every session, and does not log in', function () {
    config(['session.driver' => 'database']);
    $user = budi();
    DB::table('sessions')->insert(['id' => 'sesi-lama', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $token = Password::broker()->createToken($user);

    fromFrontend()->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'budi@example.com', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456',
    ])->assertOk()->assertExactJson(['message' => 'Password berhasil diatur ulang. Silakan masuk.']);

    expect(Hash::check('rahasiaBaru456', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    $this->assertGuest('web');
});

test('AUTH-09 token works only once', function () {
    $user = budi();
    $token = Password::broker()->createToken($user);
    $body = ['token' => $token, 'email' => 'budi@example.com', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456'];

    fromFrontend()->postJson('/api/v1/auth/reset-password', $body)->assertOk();
    assertApiError(fromFrontend()->postJson('/api/v1/auth/reset-password', $body), 422, 'INVALID_RESET_TOKEN');
});

test('AUTH-09 rejects a wrong or expired token', function (Closure $tokenFor) {
    $user = budi();
    $token = $tokenFor($user);

    assertApiError(fromFrontend()->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'budi@example.com', 'password' => 'rahasiaBaru456', 'password_confirmation' => 'rahasiaBaru456',
    ]), 422, 'INVALID_RESET_TOKEN')->assertJsonPath('message', 'Tautan reset password tidak valid atau sudah kedaluwarsa.');

    expect(Hash::check('rahasia123', $user->fresh()->password))->toBeTrue();
})->with([
    'token salah' => [fn () => 'token-palsu'],
    'lebih dari 60 menit' => [function (User $user) {
        $token = Password::broker()->createToken($user);
        test()->travel(61)->minutes();

        return $token;
    }],
]);

test('AUTH-09 checks the new password rules', function () {
    $token = Password::broker()->createToken(budi());

    assertApiError(fromFrontend()->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'budi@example.com', 'password' => 'lemah', 'password_confirmation' => 'lemah',
    ]), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors('password');
});
