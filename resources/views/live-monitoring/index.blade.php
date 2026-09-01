@extends('layouts.app')

@section('content')
    @isset($employeeDetail)
        <section class="content-stack">
            <div class="section-banner">
                <div>
                    <p class="eyebrow">Employee Live View</p>
                    <h3>{{ $employeeDetail['employee']->name }} ({{ $employeeDetail['employee_code'] }})</h3>
                    <p class="muted-copy">{{ $employeeDetail['department'] }} • {{ $employeeDetail['device_name'] }} • {{ $employeeDetail['machine_name'] }}</p>
                </div>
                <div class="topbar-actions">
                    <div class="pill">{{ $employeeDetail['status'] }}</div>
                    <a href="{{ route('live-monitoring.index') }}" class="button-secondary">Back to Overview</a>
                </div>
            </div>

            <div class="card-grid card-grid-4">
                <article class="stat-card">
                    <p>Login</p>
                    <h3>{{ $employeeDetail['login'] }}</h3>
                    <span>First login today</span>
                </article>
                <article class="stat-card">
                    <p>Logout</p>
                    <h3>{{ $employeeDetail['logout'] }}</h3>
                    <span>Open until the employee logs out</span>
                </article>
                <article class="stat-card">
                    <p>Active Time</p>
                    <h3>{{ $employeeDetail['active'] }}</h3>
                    <span>Tracked productive time today</span>
                </article>
                <article class="stat-card">
                    <p>Idle Time</p>
                    <h3>{{ $employeeDetail['idle'] }}</h3>
                    <span>Tracked idle time today</span>
                </article>
            </div>

            <div class="layout-two">
                <section class="panel-card">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Current Status</p>
                            <h3>Current App and Window</h3>
                        </div>
                    </div>

                    <div class="usage-list">
                        <div class="usage-row">
                            <div>
                                <strong>{{ $employeeDetail['current_app'] }}</strong>
                                <p>{{ $employeeDetail['current_window'] }}</p>
                            </div>
                            <span>{{ $employeeDetail['last_active'] }}</span>
                        </div>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Today Timeline</p>
                            <h3>Two-Hour Blocks</h3>
                        </div>
                    </div>

                    <div class="usage-list">
                        @foreach ($employeeDetail['timeline'] as $point)
                            <div class="usage-row">
                                <div>
                                    <strong>{{ $point['hour'] }}</strong>
                                    <p>Active {{ $point['active'] }} • Idle {{ $point['idle'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="layout-two">
                <section class="panel-card">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Recent App Activity</p>
                            <h3>Latest Windows and Apps</h3>
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
                                @forelse ($recentActivity as $activity)
                                    <tr>
                                        <td>{{ $activity['app'] }}</td>
                                        <td>{{ $activity['window'] }}</td>
                                        <td>{{ $activity['device'] }}</td>
                                        <td>{{ $activity['state'] }}</td>
                                        <td>{{ $activity['duration'] }}</td>
                                        <td>{{ $activity['time'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">No recent app activity recorded for this employee yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Recent Website Activity</p>
                            <h3>Latest Domains and Pages</h3>
                        </div>
                    </div>

                    <div class="table-shell">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Page</th>
                                    <th>Browser</th>
                                    <th>Duration</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($websiteActivity as $site)
                                    <tr>
                                        <td>{{ $site['domain'] }}</td>
                                        <td>{{ $site['title'] }}</td>
                                        <td>{{ $site['browser'] }}</td>
                                        <td>{{ $site['duration'] }}</td>
                                        <td>{{ $site['time'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">No website activity recorded for this employee yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">System Events</p>
                        <h3>Startup, Shutdown, and Session Events</h3>
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
                        <p class="muted-copy">No recent system events recorded for this employee yet.</p>
                    @endforelse
                </div>
            </section>
        </section>
    @else
        <section class="content-stack">
            

            <section class="panel-card">
                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Device</th>
                                <th>Machine</th>
                                <th>Status</th>
                                <th>Current App</th>
                                <th>Window</th>
                                <th>Last Active</th>
                                <th>Today</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($liveEmployees as $employee)
                                <tr>
                                    <td>{{ $employee['name'] }}</td>
                                    <td>{{ $employee['employee_code'] }}</td>
                                    <td>{{ $employee['device_name'] }}</td>
                                    <td>{{ $employee['machine_name'] }}</td>
                                    <td>{{ $employee['status'] }}</td>
                                    <td>{{ $employee['app'] }}</td>
                                    <td>{{ $employee['window'] }}</td>
                                    <td>{{ $employee['last_active'] }}</td>
                                    <td>{{ $employee['active'] }} active / {{ $employee['idle'] }} idle</td>
                                    <td>
                                        <a href="{{ route('live-monitoring.show', $employee['employee']) }}" class="button-link">View Details</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">Live monitoring will appear here after the Python agent sends activity logs.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    @endisset
@endsection
