@php
    $actor = request()->routeIs('customer.*')
        ? auth('customer')->user()
        : (request()->routeIs('employee.*') ? auth('employee')->user() : (auth()->user() ?? auth('employee')->user() ?? auth('customer')->user()));
    $projectDescription = trim(strip_tags($project->description ?? ''));
    $descriptionWords = str_word_count($projectDescription);
    $showDescriptionToggle = $descriptionWords > 200;
    $projectIndexRoute = request()->routeIs('customer.*')
        ? 'customer.projects.index'
        : (request()->routeIs('employee.*') ? 'employee.projects.index' : 'pm.projects.index');
    $priorityPalette = [
        'high' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
        'medium' => ['bg' => '#fef3c7', 'text' => '#b45309'],
        'low' => ['bg' => '#dcfce7', 'text' => '#166534'],
    ];
    $priorityStyle = $priorityPalette[$project->priority ?? 'medium'] ?? $priorityPalette['medium'];
@endphp
<section class="content-stack">
    <div class="section-banner">
      <div class="section-banner-copy">
            <p class="eyebrow">Project Detail</p>
            <h3>{{ $project->name }}</h3>
            <div class="topbar-actions" style="margin-top:8px;">
                <span style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;background:{{ $priorityStyle['bg'] }};color:{{ $priorityStyle['text'] }};font-size:12px;font-weight:700;">
                    Priority: {{ ucfirst($project->priority ?? 'medium') }}
                </span>
            </div>
            <div class="topbar-actions">
			<div >
			<h3>Uploaded File:</h3>
			</div>
                @forelse ($files as $file)
                    @if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'))
                        <a href="{{ route('pm.files.download', $file) }}" target="_blank" class="pill">{{ $file->original_name }}</a>
                    @else
                        <span class="pill">{{ $file->original_name }}</span>
                    @endif
                @empty
                    <span class="pill">No files uploaded</span>
                @endforelse
            </div>
            <div class="muted-copy section-banner-description {{ $showDescriptionToggle ? 'is-collapsed' : '' }}" id="project-description-{{ $project->id }}">
                {!! $project->description ?: 'No description' !!}
            </div>
            @if ($showDescriptionToggle)
                <button
                    type="button"
                    class="button-link project-description-toggle"
                    data-target="project-description-{{ $project->id }}"
                    data-more="Read more"
                    data-less="Read less"
                >
                    Read more
                </button>
            @endif
        </div>
      <div class="topbar-actions">
            @if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'))
                <a href="{{ route('pm.projects.edit', $project) }}" class="button-secondary">Edit</a>
            @endif
            <a href="{{ route($projectIndexRoute) }}" class="button-secondary">Back</a>
        </div>
    </div>

    <section class="panel-card">
        @include('pm.projects._chat_app', ['project' => $project])
    </section>

    @if (session('status'))
        <div class="notice-success">{{ session('status') }}</div>
    @endif

    {{--<div class="card-grid card-grid-4">
        <article class="stat-card"><p>Status</p><h3>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</h3></article>
        <article class="stat-card"><p>Tasks</p><h3>{{ $tasks->count() }}</h3></article>
        <article class="stat-card"><p>Bugs</p><h3>{{ $bugs->count() }}</h3></article>
        <article class="stat-card"><p>Files</p><h3>{{ $files->count() }}</h3></article>
    </div>--}}

    @if (!($actor && method_exists($actor, 'hasRole') && $actor->hasRole('customer')))
	{{-- <div class="layout-two">
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
        </section>--}}
    @endif

	{{-- <section class="panel-card">
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
    </section> --}}

	{{-- <section class="panel-card">
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
    </section> --}}

   {{-- <section class="panel-card">
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
    </section>  --}}
</section>

@if ($showDescriptionToggle)
    <script>
        (() => {
            const toggle = document.querySelector('.project-description-toggle[data-target="project-description-{{ $project->id }}"]');
            const target = document.getElementById('project-description-{{ $project->id }}');

            if (!toggle || !target) {
                return;
            }

            toggle.addEventListener('click', () => {
                const isCollapsed = target.classList.toggle('is-collapsed');
                toggle.textContent = isCollapsed ? toggle.dataset.more : toggle.dataset.less;
            });
        })();
    </script>
@endif
