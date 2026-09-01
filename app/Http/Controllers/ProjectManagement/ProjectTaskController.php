<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->assertCanAccess($project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:todo,in_progress,blocked,done'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'assigned_to_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'due_date' => ['nullable', 'date'],
            'is_visible_to_customer' => ['nullable', 'boolean'],
        ]);

        $project->tasks()->create([
            ...$payload,
            'is_visible_to_customer' => (bool) ($payload['is_visible_to_customer'] ?? false),
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(Request $request, ProjectTask $task): RedirectResponse
    {
        $this->assertCanAccess($task->project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        $payload = $request->validate([
            'status' => ['required', 'in:todo,in_progress,blocked,done'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('user')) {
            $employee = Employee::query()->where('email', $actor->email)->first();
            if (! $employee || $task->assigned_to_employee_id !== $employee->id) {
                abort(403);
            }
        }

        $task->update($payload);

        return back()->with('status', 'Task updated.');
    }

    public function storeUpdate(Request $request, ProjectTask $task): RedirectResponse
    {
        $this->assertCanAccess($task->project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('user')) {
            $employee = Employee::query()->where('email', $actor->email)->first();
            if (! $employee || $task->assigned_to_employee_id !== $employee->id) {
                abort(403);
            }
        }

        $payload = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
            'status' => ['nullable', 'in:todo,in_progress,blocked,done'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $task->updates()->create([
            ...$payload,
            'user_id' => auth()->id(),
            'is_internal' => true,
        ]);

        if (isset($payload['status']) || isset($payload['progress'])) {
            $task->update([
                'status' => $payload['status'] ?? $task->status,
                'progress' => $payload['progress'] ?? $task->progress,
            ]);
        }

        return back()->with('status', 'Task comment added.');
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
