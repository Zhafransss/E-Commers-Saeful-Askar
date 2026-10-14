<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/auth.md
 */
class AuthController extends Controller
{
    /**
     * AUTH-02 · Registrasi
     */
    public function register(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-02');
    }

    /**
     * AUTH-03 · Login
     */
    public function login(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-03');
    }

    /**
     * AUTH-04 · Logout
     */
    public function logout(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-04');
    }

    /**
     * AUTH-05 · Data user yang sedang login
     */
    public function me(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-05');
    }

    /**
     * AUTH-06 · Ubah profil
     */
    public function updateProfile(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-06');
    }

    /**
     * AUTH-07 · Ganti password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-07');
    }

    /**
     * AUTH-08 · Lupa password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-08');
    }

    /**
     * AUTH-09 · Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        throw new NotImplementedException('AUTH-09');
    }
}
