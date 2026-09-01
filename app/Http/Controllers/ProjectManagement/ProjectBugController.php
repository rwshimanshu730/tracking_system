<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectBug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectBugController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->assertCanAccess($project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
            'assigned_to_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'is_visible_to_customer' => ['nullable', 'boolean'],
        ]);

        $project->bugs()->create([
            ...$payload,
            'reported_by' => auth()->id(),
            'is_visible_to_customer' => (bool) ($payload['is_visible_to_customer'] ?? false),
        ]);

        return back()->with('status', 'Bug added.');
    }

    public function update(Request $request, ProjectBug $bug): RedirectResponse
    {
        $this->assertCanAccess($bug->project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        $payload = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ]);

        $bug->update($payload);

        return back()->with('status', 'Bug updated.');
    }

    private function assertCanAccess(Project $project): void
    {
        $user = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin', 'manager')) {
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('customer')) {
            abort_unless($project->customerMembers()->where('customers.id', $user->id)->exists(), 403);
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('user')) {
            $employee = Employee::query()->where('email', $user->email)->first();
            abort_unless($employee && $project->employeeMembers()->where('employees.id', $employee->id)->exists(), 403);
            return;
        }

        abort(403);
    }
}
