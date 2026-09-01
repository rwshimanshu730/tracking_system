@php
    $portalLayout = auth('customer')->check()
        ? 'layouts.customer'
        : ((auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('user'))
            ? 'layouts.employee'
            : 'layouts.app');
@endphp
@extends($portalLayout)

@section('content')
    @php
        $actor = auth()->user() ?? auth('customer')->user();
    @endphp
    <section class="content-stack">
        <div class="section-banner">
          <div class="section-banner-copy">
                <p class="eyebrow">Project Detail</p>
                <h3>{{ $project->name }}</h3>
                <div class="muted-copy section-banner-description">{!! $project->description ?: 'No description' !!}</div>
            </div>
          <div class="topbar-actions">
                @if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'))
                    <a href="{{ route('pm.projects.edit', $project) }}" class="button-secondary">Edit</a>
                @endif
                <a href="{{ route('pm.projects.index') }}" class="button-secondary">Back</a>
            </div>
        </div>
		
		
		
          <section class="panel-card">
                @include('pm.projects._chat_app', ['project' => $project])
            </section>

			
           
        

        @if (session('status'))
            <div class="notice-success">{{ session('status') }}</div>
        @endif

        <div class="card-grid card-grid-4">
            <article class="stat-card"><p>Status</p><h3>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</h3></article>
            <article class="stat-card"><p>Tasks</p><h3>{{ $tasks->count() }}</h3></article>
            <article class="stat-card"><p>Bugs</p><h3>{{ $bugs->count() }}</h3></article>
            <article class="stat-card"><p>Files</p><h3>{{ $files->count() }}</h3></article>
        </div>

        @if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer')))
            <div class="layout-two">
                <section class="panel-card">
                    <div class="panel-head"><h3>Add Task</h3></div>
                    <form method="POST" action="{{ route('pm.tasks.store', $project) }}" class="form-grid">
                        @csrf
                        <label class="form-field"><span>Title</span><input type="text" name="title" required></label>
                        <label class="form-field">
                            <span>Status</span>
                            <select name="status">
                                @foreach (['todo', 'in_progress', 'blocked', 'done'] as $status)
                                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field"><span>Progress %</span><input type="number" name="progress" min="0" max="100" value="0"></label>
                        <label class="form-field">
                            <span>Assign To</span>
                            <select name="assigned_to_employee_id">
                                <option value="">Unassigned</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->employee_id }}">{{ $employee->employee_name }} ({{ $employee->employee_code ?: 'No Code' }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field form-field-full"><span>Description</span><textarea name="description" rows="2"></textarea></label>
                        <label class="form-field"><input type="checkbox" name="is_visible_to_customer" value="1"> Visible to customer</label>
                        <div class="form-actions"><button type="submit" class="button-primary">Add Task</button></div>
                    </form>
                </section>

                <section class="panel-card">
                    <div class="panel-head"><h3>Report Bug</h3></div>
                    <form method="POST" action="{{ route('pm.bugs.store', $project) }}" class="form-grid">
                        @csrf
                        <label class="form-field"><span>Title</span><input type="text" name="title" required></label>
                        <label class="form-field">
                            <span>Severity</span>
                            <select name="severity">
                                @foreach (['low', 'medium', 'high', 'critical'] as $severity)
                                    <option value="{{ $severity }}">{{ ucfirst($severity) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Status</span>
                            <select name="status">
                                @foreach (['open', 'in_progress', 'resolved', 'closed'] as $status)
                                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Assign To</span>
                            <select name="assigned_to_employee_id">
                                <option value="">Unassigned</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->employee_id }}">{{ $employee->employee_name }} ({{ $employee->employee_code ?: 'No Code' }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field form-field-full"><span>Description</span><textarea name="description" rows="2"></textarea></label>
                        <label class="form-field"><input type="checkbox" name="is_visible_to_customer" value="1"> Visible to customer</label>
                        <div class="form-actions"><button type="submit" class="button-primary">Add Bug</button></div>
                    </form>
                </section>
            </div>

            <section class="panel-card">
                <div class="panel-head"><h3>Upload File</h3></div>
                <form method="POST" action="{{ route('pm.files.store', $project) }}" enctype="multipart/form-data" class="form-grid">
                    @csrf
                    <label class="form-field"><span>File</span><input type="file" name="file" required></label>
                    <label class="form-field"><input type="checkbox" name="is_visible_to_customer" value="1"> Visible to customer</label>
                    <div class="form-actions"><button type="submit" class="button-primary">Upload</button></div>
                </form>
            </section>
        @endif

        <section class="panel-card">
            <div class="panel-head"><h3>Tasks</h3></div>
            <div class="table-shell">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Status</th><th>Progress</th><th>Assignee</th><th>Visibility</th></tr></thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr>
                                <td>{{ $task->title }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $task->status)) }}</td>
                                <td>{{ $task->progress }}%</td>
                                <td>{{ $task->assigneeEmployee?->name ?? '-' }}</td>
                                <td>{{ $task->is_visible_to_customer ? 'Customer' : 'Internal' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No tasks yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-head"><h3>Bugs</h3></div>
            <div class="table-shell">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Severity</th><th>Status</th><th>Assignee</th><th>Visibility</th></tr></thead>
                    <tbody>
                        @forelse($bugs as $bug)
                            <tr>
                                <td>{{ $bug->title }}</td>
                                <td>{{ ucfirst($bug->severity) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $bug->status)) }}</td>
                                <td>{{ $bug->assigneeEmployee?->name ?? '-' }}</td>
                                <td>{{ $bug->is_visible_to_customer ? 'Customer' : 'Internal' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No bugs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-head"><h3>Files</h3></div>
            <div class="table-shell">
                <table class="data-table">
                    <thead><tr><th>File</th><th>Uploaded By</th><th>Visibility</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($files as $file)
                            <tr>
                                <td>{{ $file->original_name }}</td>
                                <td>{{ $file->uploader?->name ?? '-' }}</td>
                                <td>{{ $file->is_visible_to_customer ? 'Customer' : 'Internal' }}</td>
                                <td>
                                    <a href="{{ route('pm.files.download', $file) }}">Download</a>
                                        @if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'))
                                        <form method="POST" action="{{ route('pm.files.destroy', $file) }}" style="display:inline-block">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="button-link" onclick="return confirm('Delete file?')">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No files uploaded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
