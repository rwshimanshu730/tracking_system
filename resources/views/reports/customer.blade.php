@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Project Reports</p>
                <h3>{{ $pageTitle ?? 'Project Reports' }}</h3>
            </div>
        </div>

        <section class="panel-card">
            <div class="summary-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Total Projects</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $projectReports['total_projects'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Total Tasks</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $projectReports['total_tasks'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Completed Tasks</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $projectReports['tasks_completed'] ?? 0 }}</div>
                </div>
                <div class="summary-card panel-card" style="padding:12px;text-align:center;">
                    <span>Avg Progress</span>
                    <div style="font-size:20px;font-weight:600;margin-top:8px;">{{ $projectReports['avg_progress'] ?? 0 }}%</div>
                </div>
            </div>

            @if(!empty($projectReports['projects']))
                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Tasks</th>
                                <th>Avg Progress</th>
                                <th>Bugs</th>
                                <th>Files</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projectReports['projects'] as $p)
                                <tr>
                                    <td>{{ $p['name'] }}</td>
                                    <td>{{ $p['tasks'] }}</td>
                                    <td>{{ $p['avg_progress'] }}%</td>
                                    <td>{{ $p['bugs'] }}</td>
                                    <td>{{ $p['files'] }}</td>
                                    <td><a href="{{ route('customer.projects.show', $p['id']) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div>No project data available for the selected range.</div>
            @endif
        </section>
    </section>
@endsection
