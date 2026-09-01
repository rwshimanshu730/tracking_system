<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.projects.index');
        }

        return view('customers.auth.login');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customers.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $customer = Customer::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.projects.index');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && Str::contains($intended, ['/customerpannel'])) {
            return redirect()->to($intended);
        }

        return redirect()->route('customer.projects.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $returnToAdminDashboard = $request->session()->pull('admin_quick_login.customer', false);

        Auth::guard('customer')->logout();
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        if ($returnToAdminDashboard && Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('customer.login');
    }

    public function quickLogin(Request $request, Customer $customer): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $request->session()->put('admin_quick_login.customer', true);
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $project = Project::query()
            ->whereHas('customerMembers', function ($query) use ($customer) {
                $query->where('customers.id', $customer->id);
            })
            ->latest()
            ->first();

        if ($project) {
           // return redirect()->route('customer.projects.show', $project);
		    return redirect()->route('customer.projects.index');
        }

        return redirect()->route('customer.projects.index');
    }

    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('customers.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'pageTitle' => 'Reset Password',
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($customer));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('customer.login')->with('status', __($status));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
