<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = Employee::withCount('devices')->orderBy('name')->paginate(12);

        return view('employees.manage', [
            'pageTitle' => 'Manage Employees',
            'employees' => $employees,
        ]);
    }

    public function create(): View
    {
        return view('employees.form', [
            'pageTitle' => 'Add Employee',
            'employee' => new Employee(),
            'formAction' => route('employees.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
{
    $data = $this->validatedData($request);

    // 🔥 IMPORTANT
    if (!empty($data['password'])) {
        $data['password'] = bcrypt($data['password']);
    }

    Employee::create($data);

    return redirect()->route('employees.manage')
        ->with('status', 'Employee created successfully.');
}



    public function edit(Employee $employee): View
    {
        return view('employees.form', [
            'pageTitle' => 'Edit Employee',
            'employee' => $employee,
            'formAction' => route('employees.update', $employee),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
{
    $data = $this->validatedData($request, $employee);

    if (!empty($data['password'])) {
        $data['password'] = bcrypt($data['password']);
    } else {
        unset($data['password']);
    }

    $employee->update($data);

    return redirect()->route('employees.manage')
        ->with('status', 'Employee updated successfully.');
}

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('employees.manage')->with('status', 'Employee deleted successfully.');
    }

    private function validatedData(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'employment_status' => ['required', 'in:active,inactive,on_leave'],
            'joined_on' => ['nullable', 'date'],
			'password' => [$employee ? 'nullable' : 'required', 'confirmed', 'min:6'],
        ]);
    }
}
