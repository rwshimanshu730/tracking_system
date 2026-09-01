<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $user = $this->currentActor();
        $query = Project::query()->withCount(['tasks', 'bugs', 'files'])->latest();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('customer')) {
            $query->whereHas('customerMembers', fn ($memberQuery) => $memberQuery->where('customers.id', $user->id));
        } elseif ($user && method_exists($user, 'hasRole') && $user->hasRole('user')) {
            $employee = $this->resolveEmployeeModel($user);
            if (! $employee) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('employeeMembers', fn ($memberQuery) => $memberQuery->where('employees.id', $employee->id));
            }
        }

        return view('pm.projects.index', [
            'pageTitle' => 'Project Management',
            'projects' => $query->paginate(12),
        ]);
    }
      
    public function create(): View
    {
        return view('pm.projects.form', [
            'pageTitle' => 'Create Project',
            'project' => new Project(),
            'employees' => $this->employeeAssignmentOptions(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'selectedEmployees' => [],
            'selectedCustomers' => [],
            'formAction' => route('pm.projects.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validateProject($request);
        $project = Project::create([
            ...$payload,
            'created_by' => auth()->id(),
        ]);

        $this->syncMembers($project, $request);
        $this->storeProjectFile($project, $request);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Project $project): View
    {
        $this->authorizeAccess($project);

        $actor = $this->currentActor();
        $isCustomer = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer');
        $isAdmin = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager');
        $chatActor = $this->resolveChatActor($actor);

        $allEmployees = null;
        if ($isAdmin) {
            $allEmployees = Employee::query()->orderBy('name')->get();
        }

        $project->load(['employeeMembers', 'customerMembers', 'comments.sender', 'comments.recipient']);

        $chatMembers = $this->buildChatMembers($project, $chatActor);

        $tasks = $project->tasks()
            ->with(['assigneeEmployee', 'creator', 'updates.user'])
            ->when($isCustomer, fn ($query) => $query->where('is_visible_to_customer', true))
            ->latest()
            ->get();

        $bugs = $project->bugs()
            ->with(['reporter', 'assigneeEmployee'])
            ->when($isCustomer, fn ($query) => $query->where('is_visible_to_customer', true))
            ->latest()
            ->get();

        $files = $project->files()
            ->with('uploader')
            ->when($isCustomer, fn ($query) => $query->where('is_visible_to_customer', true))
            ->latest()
            ->get();

        return view($this->projectShowView($chatActor), [
            'pageTitle' => $project->name,
            'project' => $project,
            'tasks' => $tasks,
            'bugs' => $bugs,
            'files' => $files,
            'employees' => $this->employeeAssignmentOptions(),
            'allEmployees' => $allEmployees,
            'admins' => \App\Models\User::query()->whereIn('role', ['admin', 'manager'])->orderBy('name')->get(),
            'chatMembers' => $chatMembers,
            'chatActor' => $chatActor,
        ]);
    }

    public function edit(Project $project): View
    {
        return view('pm.projects.form', [
            'pageTitle' => 'Edit Project',
            'project' => $project->load(['employeeMembers', 'customerMembers']),
            'employees' => $this->employeeAssignmentOptions(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'selectedEmployees' => $project->employeeMembers()->pluck('employees.id')->all(),
            'selectedCustomers' => $project->customerMembers()->pluck('customers.id')->all(),
            'formAction' => route('pm.projects.update', $project),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validateProject($request));
        $this->syncMembers($project, $request);
        $this->storeProjectFile($project, $request);

        return redirect()->route('pm.projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('pm.projects.index')->with('status', 'Project deleted.');
    }

    private function validateProject(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:planned,in_progress,on_hold,completed'],
            'priority' => ['required', 'in:low,medium,high'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'project_files' => ['nullable', 'array'],
            'project_files.*' => ['file', 'max:10240'],
        ]);

        unset($validated['project_files']);

        return $validated;
    }

    private function syncMembers(Project $project, Request $request): void
    {
        $payload = $request->validate([
            'employee_members' => ['nullable', 'array'],
            'employee_members.*' => ['integer', 'exists:employees,id'],
            'customer_members' => ['nullable', 'array'],
            'customer_members.*' => ['integer', 'exists:customers,id'],
        ]);

        ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('member_role', 'employee')
            ->delete();
        ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('member_role', 'customer')
            ->delete();

        foreach (array_unique($payload['employee_members'] ?? []) as $employeeId) {
            ProjectMember::create([
                'project_id' => $project->id,
                'employee_id' => (int) $employeeId,
                'member_role' => 'employee',
            ]);
        }

        foreach (array_unique($payload['customer_members'] ?? []) as $customerId) {
            ProjectMember::create([
                'project_id' => $project->id,
                'customer_id' => (int) $customerId,
                'member_role' => 'customer',
            ]);
        }
    }

    private function authorizeAccess(Project $project): void
    {
        $user = $this->currentActor();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('admin', 'manager')) {
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('customer')) {
            abort_unless($project->customerMembers()->where('customers.id', $user->id)->exists(), 403);
            return;
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('user')) {
            $employee = $this->resolveEmployeeModel($user);
            abort_unless($employee && $project->employeeMembers()->where('employees.id', $employee->id)->exists(), 403);
            return;
        }

        abort(403);
    }

    private function employeeAssignmentOptions(): Collection
    {
        return Employee::query()
            ->orderBy('employees.name')
            ->select([
                'employees.id as employee_id',
                'employees.name as employee_name',
                'employees.employee_code',
            ])
            ->get();
    }

    private function resolveChatActor($actor): ?array
    {
        if (! $actor) {
            return null;
        }

        if ($actor instanceof Customer) {
            return [
                'type' => 'customer',
                'id' => $actor->id,
                'name' => $actor->name,
            ];
        }

        if ($actor instanceof Employee) {
            return [
                'type' => 'employee',
                'id' => $actor->id,
                'name' => $actor->name,
            ];
        }

        if ($actor instanceof User) {
            if (method_exists($actor, 'hasRole') && $actor->hasRole('user')) {
                $employee = $this->resolveEmployeeModel($actor);

                if ($employee) {
                    return [
                        'type' => 'employee',
                        'id' => $employee->id,
                        'name' => $employee->name,
                    ];
                }
            }

            return [
                'type' => 'user',
                'id' => $actor->id,
                'name' => $actor->name,
            ];
        }

        return null;
    }

    private function buildChatMembers(Project $project, ?array $chatActor): SupportCollection
    {
        $employeeMembers = $project->employeeMembers->map(function (Employee $employee) {
            return [
                'id' => $employee->id,
                'type' => 'employee',
                'name' => $employee->name,
                'subtitle' => $employee->employee_code ?: '',
                'search' => trim(($employee->employee_code ? $employee->employee_code . ' ' : '') . $employee->name),
            ];
        });

        $customerMembers = $project->customerMembers->map(function (Customer $customer) {
            return [
                'id' => $customer->id,
                'type' => 'customer',
                'name' => $customer->name,
                'subtitle' => $customer->email ?? '',
                'search' => trim($customer->name . ' ' . ($customer->email ?? '')),
            ];
        });

        return $employeeMembers
            ->concat($customerMembers)
            ->reject(function (array $member) use ($chatActor) {
                return $chatActor
                    && $member['type'] === $chatActor['type']
                    && (int) $member['id'] === (int) $chatActor['id'];
            })
            ->sortBy('name')
            ->values();
    }

    private function projectShowView(?array $chatActor): string
    {
        return match ($chatActor['type'] ?? 'user') {
            'customer' => 'pm.projects.show-customer',
            'employee' => 'pm.projects.show-employee',
            default => 'pm.projects.show-admin',
        };
    }

    private function resolveEmployeeModel($user): ?Employee
    {
        if ($user instanceof Employee) {
            return $user;
        }

        if ($user instanceof User) {
            return Employee::query()->where('email', $user->email)->first();
        }

        return null;
    }

    private function currentActor()
    {
        if (request()->routeIs('employee.*')) {
            return auth('employee')->user();
        }

        if (request()->routeIs('customer.*')) {
            return auth('customer')->user();
        }

        return auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
    }

    private function storeProjectFile(Project $project, Request $request): void
    {
        if (! $request->hasFile('project_files')) {
            return;
        }

        foreach ($request->file('project_files', []) as $uploadedFile) {
            if (! $uploadedFile) {
                continue;
            }

            $path = $uploadedFile->store('pm-files');

            $project->files()->create([
                'uploaded_by' => auth()->id(),
                'original_name' => $uploadedFile->getClientOriginalName(),
                'disk' => 'local',
                'file_path' => $path,
                'size_bytes' => $uploadedFile->getSize(),
                'is_visible_to_customer' => false,
            ]);
        }
    }
}
