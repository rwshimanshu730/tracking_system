@php
    $portalLayout = request()->routeIs('customer.*')
        ? 'layouts.customer'
        : (request()->routeIs('employee.*') ? 'layouts.employee' : 'layouts.app');
    $projectShowRoute = request()->routeIs('customer.*')
        ? 'customer.projects.show'
        : (request()->routeIs('employee.*') ? 'employee.projects.show' : 'pm.projects.show');
@endphp
@extends($portalLayout)

@section('content')
    @php
        $actor = request()->routeIs('customer.*')
            ? auth('customer')->user()
            : (request()->routeIs('employee.*') ? auth('employee')->user() : (auth()->user() ?? auth('employee')->user() ?? auth('customer')->user()));
    @endphp
    <section class="content-stack">
        
            
            @if ($actor && method_exists($actor, 'hasRole') && $actor->hasRole('admin', 'manager'))
                <a href="{{ route('pm.projects.create') }}">Create Project</a>
            @endif
       

        @if (session('status'))
            <div class="notice-success">{{ session('status') }}</div>
        @endif

        <section class="panel-card">
            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Tasks</th>
                            <th>Bugs</th>
                            <th>Files</th>
                            <th>Due Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $project)
                            @php
                                $priorityPalette = [
                                    'high' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
                                    'medium' => ['bg' => '#fef3c7', 'text' => '#b45309'],
                                    'low' => ['bg' => '#dcfce7', 'text' => '#166534'],
                                ];
                                $priorityStyle = $priorityPalette[$project->priority ?? 'medium'] ?? $priorityPalette['medium'];
                            @endphp
                            <tr>
                                <td>{{ $project->name }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</td>
                                <td>
                                    <span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:{{ $priorityStyle['bg'] }};color:{{ $priorityStyle['text'] }};font-size:12px;font-weight:700;">
                                        {{ ucfirst($project->priority ?? 'medium') }}
                                    </span>
                                </td>
                                <td>{{ $project->tasks_count }}</td>
                                <td>{{ $project->bugs_count }}</td>
                                <td>{{ $project->files_count }}</td>
                                <td>{{ $project->due_date ? \Illuminate\Support\Carbon::parse($project->due_date)->format('d M Y') : '-' }}</td>
                                <td><a href="{{ route($projectShowRoute, $project) }}">View</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No projects found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
