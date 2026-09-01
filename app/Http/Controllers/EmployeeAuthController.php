<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('employee.projects.index');
        }

        return view('employees.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_code' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'password' => ['required'],
        ]);

        if (empty($data['employee_code']) && empty($data['email'])) {
            return back()->withErrors([
                'employee_code' => 'Please enter employee code or email',
            ])->withInput();
        }

        $employee = ! empty($data['employee_code'])
            ? Employee::where('employee_code', $data['employee_code'])->first()
            : Employee::where('email', $data['email'])->first();

        if (! $employee) {
            return back()->withErrors([
                'login' => 'Employee not found',
            ])->withInput();
        }

        if (! \Hash::check($data['password'], $employee->password)) {
            return back()->withErrors([
                'password' => 'Invalid password',
            ])->withInput();
        }

        Auth::guard('employee')->login($employee);
        $request->session()->regenerate();

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && Str::contains($intended, ['/employeepanel'])) {
            return redirect()->to($intended);
        }

        return redirect()->route('employee.projects.index');
    }

    public function quickLogin(Request $request, Employee $employee): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $request->session()->put('admin_quick_login.employee', true);
        }

        Auth::guard('employee')->login($employee);
        $request->session()->regenerate();

        $project = Project::query()
            ->whereHas('employeeMembers', function ($query) use ($employee) {
                $query->where('employees.id', $employee->id);
            })
            ->latest()
            ->first();

        if ($project) {
           // return redirect()->route('employee.projects.show', $project);
		    return redirect()->route('employee.projects.index');
        }

        return redirect()->route('employee.projects.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $returnToAdminDashboard = $request->session()->pull('admin_quick_login.employee', false);

        Auth::guard('employee')->logout();
        $request->session()->forget('url.intended');
        $request->session()->regenerateToken();

        if ($returnToAdminDashboard && Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('employee.login');
    }
}
