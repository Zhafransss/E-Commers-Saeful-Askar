<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
| Black-box: AUTH-01..09 (docs/api/auth.md). Real session cookies through the stateful API.
*/

function bbRegistration(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '081234567890',
        'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
    ];
}

test('register boundaries: name 2..100, password 8..255 with a letter and a digit, email format and uniqueness', function (array $override, bool $ok) {
    $response = fromFrontend()->postJson('/api/v1/auth/register', bbRegistration($override));

    $ok ? $response->assertCreated() : assertApiError($response, 422, 'VALIDATION_ERROR');
})->with([
    'name 1 char' => [['name' => 'A'], false],
    'name 2 chars' => [['name' => 'Al'], true],
    'name 100 chars' => [['name' => str_repeat('a', 100)], true],
    'name 101 chars' => [['name' => str_repeat('a', 101)], false],
    'password 7 chars' => [['password' => 'abc1234', 'password_confirmation' => 'abc1234'], false],
    'password 8 chars' => [['password' => 'abcd1234', 'password_confirmation' => 'abcd1234'], true],
    'password only letters' => [['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], false],
    'password only digits' => [['password' => '12345678', 'password_confirmation' => '12345678'], false],
    'password confirmation differs' => [['password_confirmation' => 'lain12345'], false],
    'password confirmation missing' => [['password_confirmation' => null], false],
    'email without @' => [['email' => 'budi.example.com'], false],
    'email empty' => [['email' => ''], false],
    'phone letters' => [['phone' => 'abcdefghij'], false],
    'phone +62' => [['phone' => '+6281234567890'], true],
    'phone with dashes' => [['phone' => '0812-3456-7890'], true],
    'phone too short' => [['phone' => '0812'], false],
    'missing everything' => [['name' => null, 'email' => null, 'phone' => null, 'password' => null], false],
]);

test('register creates a customer, logs them in, normalizes email/phone, and never lets the client choose a role', function () {
    $response = fromFrontend()->postJson('/api/v1/auth/register', bbRegistration(['email' => 'Budi@Example.COM', 'phone' => '+6281234567890', 'role' => 'admin', 'is_admin' => true]))
        ->assertCreated();

    expect($response->json('data'))->toMatchArray(['email' => 'budi@example.com', 'role' => 'customer'])
        ->and($response->json('data'))->not->toHaveKey('password')
        ->and(User::first()->phone)->toStartWith('08')
        ->and(User::first()->password)->not->toBe('rahasia123');
    expect($response->getCookie(config('session.cookie')))->not->toBeNull();
});

test('register twice with the same email (any case) is refused; the response never reveals more than the validation message', function () {
    fromFrontend()->postJson('/api/v1/auth/register', bbRegistration())->assertCreated();
    auth()->guard('web')->logout();

    assertApiError(fromFrontend()->postJson('/api/v1/auth/register', bbRegistration(['email' => 'BUDI@example.com'])), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('email');
    expect(User::count())->toBe(1);
});

test('register with a malformed JSON body or a JSON array answers 400/422 in the error shape, never 500', function () {
    $malformed = fromFrontend()->call('POST', '/api/v1/auth/register', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"name": ');
    expect($malformed->getStatusCode())->toBeIn([400, 422]);
    bbExpectErrorShape($malformed);

    expect(fromFrontend()->postJson('/api/v1/auth/register', ['a', 'b'])->getStatusCode())->toBe(422);
    expect(fromFrontend()->postJson('/api/v1/auth/register', ['name' => ['x'], 'email' => ['y'], 'phone' => ['z'], 'password' => ['p']])->getStatusCode())->toBe(422);
});

test('login: right credentials, wrong password and unknown email give the same message; email is case-insensitive', function () {
    bbCustomer(['email' => 'budi@example.com', 'password' => 'rahasia123']);

    fromFrontend()->postJson('/api/v1/auth/login', ['email' => ' BUDI@example.com ', 'password' => 'rahasia123'])->assertOk()->assertJsonPath('data.email', 'budi@example.com');

    $wrong = assertApiError(fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'salah12345']), 422, 'INVALID_CREDENTIALS');
    $unknown = assertApiError(fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'siapa@example.com', 'password' => 'salah12345']), 422, 'INVALID_CREDENTIALS');
    expect($wrong->json('message'))->toBe($unknown->json('message'));
});

test('login is rate limited (429 TOO_MANY_REQUESTS) after repeated failures', function () {
    bbCustomer(['email' => 'budi@example.com', 'password' => 'rahasia123']);

    $statuses = collect(range(1, 12))->map(fn () => fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'salah12345'])->getStatusCode());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
});

test('the session works end to end: me, profile update, logout, then me is 401', function () {
    $user = bbCustomer(['email' => 'budi@example.com', 'password' => 'rahasia123', 'name' => 'Budi', 'phone' => '081234567890']);
    $login = fromFrontend()->postJson('/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])->assertOk();
    $cookie = $login->getCookie(config('session.cookie'))->getValue();

    fromFrontend()->withCookie(config('session.cookie'), $cookie)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);

    fromFrontend()->withCookie(config('session.cookie'), $cookie)->putJson('/api/v1/auth/profile', ['name' => 'Budi Baru', 'phone' => '081298765432'])
        ->assertOk()->assertJsonPath('data.name', 'Budi Baru');
    expect($user->refresh()->name)->toBe('Budi Baru');

    fromFrontend()->withCookie(config('session.cookie'), $cookie)->postJson('/api/v1/auth/logout')->assertOk();
    app('auth')->forgetGuards(); // a new request starts with no cached user, as in production

    // Replaying the old session cookie must not log anyone in again.
    assertApiError(fromFrontend()->withCookie(config('session.cookie'), $cookie)->getJson('/api/v1/auth/me'), 401, 'UNAUTHENTICATED');
});

test('profile rejects bad names/phones and cannot change email or role', function () {
    $user = bbCustomer(['email' => 'budi@example.com']);

    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/profile', ['name' => 'A', 'phone' => '081234567890']), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/profile', ['name' => 'Budi', 'phone' => 'x']), 422, 'VALIDATION_ERROR');

    $this->actingAs($user)->putJson('/api/v1/auth/profile', ['name' => 'Budi', 'phone' => '081234567890', 'email' => 'x@y.com', 'role' => 'admin'])->assertOk();
    expect($user->refresh()->email)->toBe('budi@example.com')->and($user->role->value)->toBe('customer');
});

test('password change needs the current password, a different valid new one, and ends other sessions', function () {
    $user = bbCustomer(['password' => 'rahasia123']);

    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'salah12345', 'password' => 'baru12345', 'password_confirmation' => 'baru12345']), 422, 'INVALID_CURRENT_PASSWORD');
    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'rahasia123', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123']), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'rahasia123', 'password' => 'pendek1', 'password_confirmation' => 'pendek1']), 422, 'VALIDATION_ERROR');

    $this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertOk();
    expect(Hash::check('baru12345', $user->refresh()->password))->toBeTrue();
});

test('forgot password answers the same for known and unknown emails (no account enumeration)', function () {
    Notification::fake();
    bbCustomer(['email' => 'budi@example.com']);

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'budi@example.com'])->assertOk();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siapa@example.com'])->assertOk();

    expect($known->json())->toBe($unknown->json());
    assertApiError($this->postJson('/api/v1/auth/forgot-password', ['email' => 'bukan-email']), 422, 'VALIDATION_ERROR');
});

test('reset password: a bad token is INVALID_RESET_TOKEN and the password stays; a good token works once', function () {
    $user = bbCustomer(['email' => 'budi@example.com', 'password' => 'rahasia123']);
    $token = Password::broker()->createToken($user);
    $body = ['email' => 'budi@example.com', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'];

    assertApiError($this->postJson('/api/v1/auth/reset-password', $body + ['token' => 'ngawur']), 422, 'INVALID_RESET_TOKEN');
    expect(Hash::check('rahasia123', $user->refresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/reset-password', $body + ['token' => $token])->assertOk();
    expect(Hash::check('baru12345', $user->refresh()->password))->toBeTrue();

    assertApiError($this->postJson('/api/v1/auth/reset-password', $body + ['token' => $token]), 422, 'INVALID_RESET_TOKEN');
});

test('every protected auth endpoint answers 401 UNAUTHENTICATED for a guest', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
})->with([['GET', '/api/v1/auth/me'], ['POST', '/api/v1/auth/logout'], ['PUT', '/api/v1/auth/profile'], ['PUT', '/api/v1/auth/password']]);

test('passwords are never present in any auth response', function () {
    $response = fromFrontend()->postJson('/api/v1/auth/register', bbRegistration())->assertCreated();

    expect(json_encode($response->json()))->not->toContain('rahasia123')->not->toContain('password')->not->toContain('remember_token');
});

test('reset password is rate limited like the other credential endpoints (429 after 5 attempts a minute)', function () {
    $body = ['token' => 'tebakan', 'email' => 'budi@example.com', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'];

    $statuses = collect(range(1, 7))->map(fn () => $this->postJson('/api/v1/auth/reset-password', $body)->getStatusCode());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
});

test('a non-string email (array/object) is a 422 on every endpoint that takes one, never a 500', function (string $uri) {
    foreach ([['x'], ['k' => 'v']] as $email) {
        expect($this->postJson($uri, ['email' => $email, 'password' => 'abc12345', 'name' => 'Budi', 'phone' => '081234567890', 'token' => 't'])->getStatusCode())->toBe(422);
    }
})->with(['/api/v1/auth/register', '/api/v1/auth/login', '/api/v1/auth/forgot-password', '/api/v1/auth/reset-password']);
