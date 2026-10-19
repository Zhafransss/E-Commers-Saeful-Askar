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
    public function login(Request $request): View
    {
        return view('pages.auth.login', ['intended' => $this->intended($request)]);
    }

    public function register(Request $request): View
    {
        return view('pages.auth.register', ['intended' => $this->intended($request)]);
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

    /**
     * The page to return to after logging in: `?next=` (set by "add to cart" for a guest) or
     * the page the guard (RequirePageRole) sent the guest away from. Only pages of this site
     * are passed on, never an arbitrary redirect.
     */
    private function intended(Request $request): ?string
    {
        $next = $request->query('next');
        // A path of this site: "/x", not "//host" or "/\host" (which browsers read as another site).
        if (is_string($next) && str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\')) {
            return url($next);
        }

        $url = session('url.intended');

        return is_string($url) && str_starts_with($url, url('/')) ? $url : null;
    }
}
