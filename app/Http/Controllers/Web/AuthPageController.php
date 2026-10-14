<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Auth pages only render forms; login/register/reset happen through AUTH-02 – AUTH-09.
 */
class AuthPageController extends Controller
{
    public function login(): View
    {
        return view('pages.auth.login');
    }

    public function register(): View
    {
        return view('pages.auth.register');
    }

    public function forgotPassword(): View
    {
        return view('pages.auth.forgot-password');
    }

    /**
     * Link from the AUTH-08 email: /reset-password?token=…&email=…
     */
    public function resetPassword(Request $request): View
    {
        return view('pages.auth.reset-password', [
            'token' => (string) $request->query('token'),
            'email' => (string) $request->query('email'),
        ]);
    }
}
