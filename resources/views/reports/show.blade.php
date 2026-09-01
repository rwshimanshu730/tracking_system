@php
    $portalLayout = request()->routeIs('customer.*')
        ? 'layouts.customer'
        : (request()->routeIs('employee.*') ? 'layouts.employee' : 'layouts.app');
    $reportsIndexRoute = request()->routeIs('customer.*')
        ? 'customer.reports.index'
        : (request()->routeIs('employee.*') ? 'employee.reports.index' : 'reports.index');
@endphp
@extends($portalLayout)

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Employee Report</p>
                <h3>{{ $employee->name }} ({{ $employee->employee_code }})</h3>
                <p class="muted-copy">Range: {{ $selectedRangeLabel }}</p>
            </div>
            <div class="topbar-actions">
                <a href="{{ route($reportsIndexRoute, ['preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}" class="button-secondary">Back to Reports</a>
            </div>
        </div>

        <div class="card-grid card-grid-4">
            <article class="stat-card">
                <p>First Login</p>
                <h3>{{ $summary['login'] }}</h3>
                <span>First login in selected range</span>
            </article>
            <article class="stat-card">
                <p>Last Logout</p>
                <h3>{{ $summary['logout'] }}</h3>
                <span>Open until the session ends</span>
            </article>
            <article class="stat-card">
                <p>Active Time</p>
                <h3>{{ $summary['active'] }}</h3>
                <span>Total productive time</span>
            </article>
            <article class="stat-card">
                <p>Idle Time</p>
                <h3>{{ $summary['idle'] }}</h3>
                <span>Total idle time</span>
            </article>
            <article class="stat-card">
                <p>Manual Time</p>
                <h3>{{ $summary['manual'] ?? '00:00:00' }}</h3>
                <span>Manual entries added in selected range</span>
            </article>
        </div>

		{{--  <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Summary</p>
                    <h3>Range Overview</h3>
                </div>
            </div>

            <div class="usage-list">
                <div class="usage-row">
                    <div>
                        <strong>Total Time</strong>
                        <p>Combined active and idle usage</p>
                    </div>
                    <span>{{ $summary['total'] }}</span>
                </div>
                <div class="usage-row">
                    <div>
                        <strong>Devices</strong>
                        <p>Devices used in this selected range</p>
                    </div>
                    <span>{{ $summary['devices'] }}</span>
                </div>
            </div>
        </section> --}}
		
		
		<section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Days By</p>
                    <h3>Days By</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
    <tr>
        <th>Date</th>
        <th>Login</th>
        <th>Logout</th>
        <th>Active</th>
        <th>Idle</th>
        <th>Manual</th>
        <th>Action</th>
    </tr>
</thead>

<tbody>
@forelse($sessions as $date => $daySessions)

  @php
    $dateComment = $reportCommentsByDate[$date] ?? null;
    // 🔥 LOGIN (already correct)
    $firstActivity = \App\Models\ActivityLog::where('employee_id', $employee->id)
        ->whereDate('started_at', $date)
        ->where('activity_type', '!=', 'idle')
        ->orderBy('started_at')
        ->value('started_at');

    $login = $firstActivity ?? optional($daySessions->sortBy('started_at')->first())->started_at;

    // 🔥 LOGOUT (NEW FIX)
    $lastActivity = \App\Models\ActivityLog::where('employee_id', $employee->id)
        ->whereDate('started_at', $date)
        ->where('activity_type', '!=', 'idle')
        ->selectRaw('MAX(COALESCE(ended_at, started_at)) as last_time')
        ->value('last_time');

    $logout = $lastActivity ?? $daySessions->pluck('ended_at')->filter()->sort()->last();

    $active = \App\Models\ActivityLog::where('employee_id', $employee->id)
    ->whereDate('started_at', $date)
    ->where('activity_type', '!=', 'idle')
    ->sum('duration_seconds');

$idle = \App\Models\ActivityLog::where('employee_id', $employee->id)
    ->whereDate('started_at', $date)
    ->where('activity_type', 'idle')
    ->sum('duration_seconds');

$manual = (int) \App\Models\ManualTimeEntry::where('employee_id', $employee->id)
    ->whereDate('entry_date', $date)
    ->sum(\Illuminate\Support\Facades\DB::raw('minutes * 60'));
@endphp

    <tr class="{{ $selectedDate == $date ? 'bg-gray-200' : '' }}">
        <td>{{ $date }}</td>
        <td>{{ $login ? \Carbon\Carbon::parse($login)->format('H:i') : '-' }}</td>
        <td>{{ $logout ? \Carbon\Carbon::parse($logout)->format('H:i') : 'Open' }}</td>
        <td>{{ gmdate('H:i:s', $active) }}</td>
        <td>{{ gmdate('H:i:s', $idle) }}</td>
        <td>{{ gmdate('H:i:s', $manual) }}</td>

			{{-- <td>
            <a href="{{ route('reports.show', [
                'employee' => $employee->id,
                'preset' => $selectedPreset,
                'from' => $selectedFrom,
                'to' => $selectedTo,
                'date' => $date
            ]) }}">
                View Details
            </a>
			</td>--}}
			<td>
    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
        <button onclick="toggleDetails('{{ $date }}')" class="button-link">
            View Details
        </button>
        @if($dateComment && filled($dateComment->comment))
            <div style="font-size:12px;line-height:1.5;color:#475569;">
                <strong>Comment:</strong> {{ $dateComment->comment }}
            </div>
        @endif
    </div>
</td>
    </tr>
	
	<tr id="details-{{ $date }}" style="display: none;">
    <td colspan="7">
        <div class="p-3 bg-gray-100 rounded">
            <h4>Top 10 Websites</h4>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Time Spent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($websiteByDate[$date] ?? [] as $site)
                        <tr>
                            <td>{{ $site->domain }}</td>
                            <td>{{ gmdate('H:i:s', $site->total_seconds) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>

@empty
    <tr>
        <td colspan="7">No Date Found.</td>
    </tr>
@endforelse
</tbody>
                </table>
            </div>
        </section>
		
		
		{{-- 🔥 ADD THIS HERE 
@if($selectedDate)
    <section class="panel-card">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Day Details</p>
                <h3>Details for {{ $selectedDate }}</h3>
            </div>
        </div>

        <div class="table-shell">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Start</th>
                        <th>End</th>
                        <th>Device</th>
                        <th>Active</th>
                        <th>Idle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daySessions as $session)
                        <tr>
                            <td>{{ $session->started_at }}</td>
                            <td>{{ $session->ended_at ?? 'Open' }}</td>
                            <td>{{ $session->device->device_name ?? '-' }}</td>
                            <td>{{ $session->active_seconds ?? 0 }}</td>
                            <td>{{ $session->idle_seconds ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No data for this day</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
	--}}	
		 <div class="layout-two">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Application Usage</p>
                        <h3>Apps and Time Spent</h3>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Category</th>
                                <th>Time Spent</th>
                                <th>Last Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appUsage as $app)
                                <tr>
                                    <td>{{ $app['app'] }}</td>
                                    <td>{{ $app['category'] }}</td>
                                    <td>{{ $app['duration'] }}</td>
                                    <td>{{ $app['last_used'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No app usage found for this employee in the selected range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Website Usage</p>
                        <h3>Domains and Browser Time</h3>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Browser</th>
                                <th>Time Spent</th>
                                <th>Last Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($websiteUsage as $site)
                                <tr>
                                    <td>{{ $site['domain'] }}</td>
                                    <td>{{ $site['browser'] }}</td>
                                    <td>{{ $site['duration'] }}</td>
                                    <td>{{ $site['last_used'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">No website usage found for this employee in the selected range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="layout-two">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Recent Activity</p>
                        <h3>Timeline of Apps and Windows</h3>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>App</th>
                                <th>Window</th>
                                <th>Device</th>
                                <th>State</th>
                                <th>Duration</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activityTimeline as $activity)
                                <tr>
                                    <td>{{ $activity['app'] }}</td>
                                    <td>{{ $activity['window'] }}</td>
                                    <td>{{ $activity['device'] }}</td>
                                    <td>{{ $activity['type'] }}</td>
                                    <td>{{ $activity['duration'] }}</td>
                                    <td>{{ $activity['time'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">No detailed activity timeline found for this employee in the selected range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">System Events</p>
                        <h3>Start, Stop, and Session Events</h3>
                    </div>
                </div>

                <div class="usage-list">
                    @forelse ($systemEvents as $event)
                        <div class="usage-row">
                            <div>
                                <strong>{{ $event['event'] }}</strong>
                                <p>{{ $event['device'] }}</p>
                            </div>
                            <span>{{ $event['time'] }}</span>
                        </div>
                    @empty
                        <p class="muted-copy">No system events found for this employee in the selected range.</p>
                    @endforelse
                </div>
            </section>
        </div>

		{{--    <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">IP Audit</p>
                    <h3>Network Trail</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>IP Address</th>
                            <th>Source</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ipAuditLogs as $log)
                            <tr>
                                <td>{{ $log['device'] }}</td>
                                <td>{{ $log['ip_address'] }}</td>
                                <td>{{ $log['source'] }}</td>
                                <td>{{ $log['time'] }}</td>
                                <td>{{ $log['changed'] ? 'Changed' : 'Same IP' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No IP audit logs found for this employee in the selected range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section> --}}
    </section>
	
	<script>
function toggleDetails(date) {
    let row = document.getElementById('details-' + date);

    if (row.style.display === 'none') {
        row.style.display = 'table-row';
    } else {
        row.style.display = 'none';
    }
}
</script>
@endsection
