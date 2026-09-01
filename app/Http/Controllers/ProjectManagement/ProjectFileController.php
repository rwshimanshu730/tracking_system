<?php

namespace App\Http\Controllers\ProjectManagement;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectFileController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->assertCanAccess($project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        abort_if($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer'), 403);

        $payload = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'is_visible_to_customer' => ['nullable', 'boolean'],
        ]);

        $uploadedFile = $payload['file'];
        $path = $uploadedFile->store('pm-files');

        $project->files()->create([
            'uploaded_by' => auth()->id(),
            'original_name' => $uploadedFile->getClientOriginalName(),
            'disk' => 'local',
            'file_path' => $path,
            'size_bytes' => $uploadedFile->getSize(),
            'is_visible_to_customer' => (bool) ($payload['is_visible_to_customer'] ?? false),
        ]);

        return back()->with('status', 'File uploaded.');
    }

    /* public function download(ProjectFile $file): StreamedResponse
    {
        $this->assertCanAccess($file->project);
        $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer') && ! $file->is_visible_to_customer) {
            abort(403);
        }
		 $path = Storage::disk($file->disk)->path($file->file_path);

    return response()->file($path);

        //return Storage::disk($file->disk)->download($file->file_path, $file->original_name);
    } */
	
	public function download(ProjectFile $file): BinaryFileResponse
{
    $this->assertCanAccess($file->project);

    $actor = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();

    if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer') && ! $file->is_visible_to_customer) {
        abort(403);
    }

    $path = Storage::disk($file->disk)->path($file->file_path);

    return response()->file($path);
}

    public function destroy(ProjectFile $file): RedirectResponse
    {
        $this->assertCanAccess($file->project);
        Storage::disk($file->disk)->delete($file->file_path);
        $file->delete();

        return back()->with('status', 'File deleted.');
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
