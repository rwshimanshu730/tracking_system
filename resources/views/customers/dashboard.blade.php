<!-- customer dashboard -->
@extends('layouts.customer')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Customer Dashboard</p>
                <h3>{{ $pageTitle ?? 'Dashboard' }}</h3>
            </div>
        </div>

        <section class="panel-card">
            <div class="summary-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Total Projects</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $reports['total_projects'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Total Tasks</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $reports['total_tasks'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Completed Tasks</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $reports['tasks_completed'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Avg Progress</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $reports['avg_progress'] ?? 0 }}%</div>
                </div>
            </div>

            @if($projects->count())
                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Tasks</th>
                                <th>Bugs</th>
                                <th>Files</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projects as $project)
                                <tr>
                                    <td>{{ $project->name }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $project->status)) }}</td>
                                    <td>{{ $project->tasks_count }}</td>
                                    <td>{{ $project->bugs_count }}</td>
                                    <td>{{ $project->files_count }}</td>
                                    <td><a href="{{ route('customer.projects.show', $project) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px;">{{ $projects->links() }}</div>
            @else
                <div>No projects assigned to you yet.</div>
            @endif
        </section>
    </section>
@endsection
