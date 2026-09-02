@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="card-grid">
            @foreach ($summaryCards as $card)
                <article class="stat-card">
                    <p>{{ $card['label'] }}</p>
                    <h3>{{ $card['value'] }}</h3>
                    <span>{{ $card['trend'] }}</span>
                </article>
            @endforeach
        </div>

        <div class="layout-two">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Today Snapshot</p>
                        <h3>Team Activity Timeline</h3>
                    </div>
                    <span class="pill">Live overview</span>
                </div>

                <div class="timeline-chart">
                    @forelse ($todayTimeline as $point)
                        <div class="timeline-row">
                            <span>{{ $point['hour'] }}</span>
                            <div class="timeline-bars">
                                <div class="timeline-bar timeline-bar-active" style="width: {{ $point['active'] * 4 }}%"></div>
                                <div class="timeline-bar timeline-bar-idle" style="width: {{ $point['idle'] * 4 }}%"></div>
                            </div>
                            <strong>{{ $point['active'] }} active / {{ $point['idle'] }} idle</strong>
                        </div>
                    @empty
                        <p class="muted-copy">No activity has been recorded yet for today.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">App Mix</p>
                        <h3>Usage Share</h3>
                    </div>
                </div>

                <div class="usage-list">
                    @forelse ($appUsage as $app)
                        <div class="usage-row">
                            <div>
                                <strong>{{ $app['app'] }}</strong>
                                <p>{{ $app['duration'] }}</p>
                            </div>
                            <span>{{ $app['share'] }}</span>
                        </div>
                    @empty
                        <p class="muted-copy">App usage will appear here after the agent sends logs.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Website Mix Himanshu yadav</p>
                    <h3>Top Domains Today</h3>
                </div>
            </div>

            <div class="usage-list">
                @forelse ($websiteUsage as $site)
                    <div class="usage-row">
                        <div>
                            <strong>{{ $site['domain'] }}</strong>
                            <p>{{ $site['duration'] }}</p>
                        </div>
                        <span>{{ $site['share'] }}</span>
                    </div>
                @empty
                    <p class="muted-copy">Website tracking will appear here after the browser extension syncs URLs and domains.</p>
                @endforelse
            </div>
        </section>

        <div class="layout-two layout-two-wide">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Top Performance</p>
                        <h3>Employee Summary</h3>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Productive</th>
                                <th>Idle</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topEmployees as $employee)
                                <tr>
                                    <td>{{ $employee['name'] }}</td>
                                    <td>{{ $employee['department'] }}</td>
                                    <td>{{ $employee['productive'] }}</td>
                                    <td>{{ $employee['idle'] }}</td>
                                    <td><span class="status-dot">{{ $employee['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">No employee activity has been recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Department Health</p>
                        <h3>Current Team Efficiency</h3>
                    </div>
                </div>

                <div class="snapshot-stack">
                    @forelse ($teamSnapshots as $snapshot)
                        <article class="snapshot-card">
                            <div>
                                <p>{{ $snapshot['name'] }}</p>
                                <strong>{{ $snapshot['active'] }}</strong>
                            </div>
                            <span>{{ $snapshot['efficiency'] }}</span>
                        </article>
                    @empty
                        <p class="muted-copy">Department summaries will appear after employees and sessions are available.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
@endsection
