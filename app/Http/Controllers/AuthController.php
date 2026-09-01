<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = auth()->user();
            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('customer')) {
                    return redirect()->route('customer.dashboard');
                }
                if ($user->hasRole('user')) {
                    return redirect()->route('pm.projects.index');
                }
            }

            return redirect()->route('dashboard');
        }

        if (\App\Models\User::count() === 0) {
            return redirect()->route('setup');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = auth()->user();

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('customer')) {
                return redirect()->intended(route('customer.dashboard'));
            }

            if ($user->hasRole('user')) {
                return redirect()->intended(route('pm.projects.index'));
            }
        }

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && ! Str::contains($intended, ['/customerpannel', '/employeepanel'])) {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
