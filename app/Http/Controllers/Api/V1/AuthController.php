<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\UserSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Contract: docs/api/auth.md. Session cookie auth (Sanctum stateful) on the
 * `web` guard; AUTH-01 (/sanctum/csrf-cookie) comes from Sanctum itself.
 */
class AuthController extends Controller
{
    /**
     * AUTH-02 · Registrasi
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => PhoneNumber::normalize($request->validated('phone')),
            'password' => $request->validated('password'),
        ]);
        // Not mass assignable on purpose; the Jubelio contact is made at the first checkout.
        $user->role = UserRole::Customer;
        $user->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * AUTH-03 · Login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        // One message for an unknown email and a wrong password, so the
        // endpoint never reveals which emails have an account.
        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw new ApiException('Email atau password salah.', 'INVALID_CREDENTIALS', 422);
        }

        $request->session()->regenerate();

        return $this->user(Auth::guard('web')->user());
    }

    /**
     * AUTH-04 · Logout. The cart stays in the database for the next login.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /**
     * AUTH-05 · Data user yang sedang login
     */
    public function me(Request $request): JsonResponse
    {
        return $this->user($request->user());
    }

    /**
     * AUTH-06 · Ubah profil. Updating the Jubelio contact (INT-JB-09) comes with
     * Fase 4, since jubelio_contact_id is only filled at the first checkout.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'name' => $request->validated('name'),
            'phone' => PhoneNumber::normalize($request->validated('phone')),
        ]);

        return $this->user($user);
    }

    /**
     * AUTH-07 · Ganti password. Other devices are signed out; this one stays.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw new ApiException('Password saat ini salah.', 'INVALID_CURRENT_PASSWORD', 422);
        }

        $user->update(['password' => $request->validated('password')]);

        $guard = Auth::guard('web');
        $remembered = $request->cookies->has($guard->getRecallerName());
        UserSessions::endAll($user, $request->hasSession() ? $request->session()->getId() : null);

        // The remember token just rotated: hand this device a fresh cookie.
        if ($remembered) {
            $guard->login($user, remember: true);
        }

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    /**
     * AUTH-08 · Lupa password. Same answer whether the email exists or not,
     * and also when a link was sent moments ago (broker throttle).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink($request->safe()->only('email'));

        return response()->json(['message' => 'Jika email terdaftar, tautan reset password sudah dikirim.']);
    }

    /**
     * AUTH-09 · Reset password. Every session of the user ends; no auto-login.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password): void {
                $user->update(['password' => $password]);
                UserSessions::endAll($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException('Tautan reset password tidak valid atau sudah kedaluwarsa.', 'INVALID_RESET_TOKEN', 422);
        }

        return response()->json(['message' => 'Password berhasil diatur ulang. Silakan masuk.']);
    }

    /**
     * 200 with the User object. Set explicitly: JsonResource answers 201 for a
     * model created in the same request (registration followed by login).
     */
    private function user(User $user): JsonResponse
    {
        return (new UserResource($user))->response()->setStatusCode(200);
    }
}
