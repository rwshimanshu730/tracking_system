@php
    $portalLayout = request()->routeIs('customer.*')
        ? 'layouts.customer'
        : (request()->routeIs('employee.*') ? 'layouts.employee' : 'layouts.app');
    $reportsIndexRoute = request()->routeIs('customer.*')
        ? 'customer.reports.index'
        : (request()->routeIs('employee.*') ? 'employee.reports.index' : 'reports.index');
    $reportsShowRoute = request()->routeIs('customer.*')
        ? 'customer.reports.show'
        : (request()->routeIs('employee.*') ? 'employee.reports.show' : 'reports.show');
    $canManageReportComments = !request()->routeIs('customer.*') && !request()->routeIs('employee.*');
@endphp
@extends($portalLayout)

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Range Filter</p>
                <h3>Range: {{ $selectedRangeLabel }}</h3>
            </div>
            <div class="report-filter-row">
               
                    <a href="{{ route($reportsIndexRoute, ['preset' => 'today']) }}" class="button-secondary">Today</a>
                    <a href="{{ route($reportsIndexRoute, ['preset' => 'yesterday']) }}" class="button-secondary">Yesterday</a>
                    <a href="{{ route($reportsIndexRoute, ['preset' => 'this_week']) }}" class="button-secondary">This Week</a>
                    <a href="{{ route($reportsIndexRoute, ['preset' => 'this_month']) }}" class="button-secondary">This Month</a>
               
                <form method="GET" action="{{ route($reportsIndexRoute) }}" class="topbar-actions report-filter-form">
                    <input type="hidden" name="preset" value="custom">
                    <input type="date" name="from" value="{{ $selectedFrom }}" class="date-input">
                    <input type="date" name="to" value="{{ $selectedTo }}" class="date-input">
                    <button type="submit" class="button-primary">Apply Custom Range</button>
                </form>
            </div>
        </div>

      {{--   <div class="card-grid">
            @foreach ($reportCards as $card)
                <article class="panel-card">
                    <p class="eyebrow">Report Module</p>
                    <h3>{{ $card['title'] }}</h3>
                    <p class="muted-copy">{{ $card['detail'] }}</p>
                </article>
            @endforeach
	  </div> --}}

        <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Attendance Summary</p>
                    <h3>Employee Login and Logout for {{ $selectedRangeLabel }}</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Employee Code</th>
                            <th>First Login</th>
                            <th>Last Logout</th>
                            <th>In-Out</th>
                            <th>Active</th>
                            <th>Idle</th>
                            <th>Manual</th>
                            <th>Total</th>
                            <th>Work Time</th>
                            <th>Device</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employeeSessions as $session)
                            <tr>
                                <td>{{ $session['employee'] }}</td>
                                <td>{{ $session['employee_code'] }}</td>
                                <td>{{ $session['login'] }}</td>
                                <td>{{ $session['logout'] }}</td>
                                <td>{{ $session['in_out'] }}</td>
                                <td>{{ $session['active'] }}</td>
                                <td>{{ $session['idle'] }}</td>
                                <td>{{ $session['manual'] ?? '00:00:00' }}</td>
                                <td>
                                    {{ $session['total'] }}
                                   
                                </td>
                                <td>{{ $session['work_time'] }}</td>
                                <td>{{ $session['devices'] }}</td>
                                <td>
                                    @if ($session['employee_id'])
                                        <a
                                            href="{{ route($reportsShowRoute, ['employee' => $session['employee_id'], 'preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}"
                                            class="button-link"
                                        >
                                            View Details
                                        </a>
                                        @if ($canManageReportComments)
                                            <button
                                                type="button"
                                                class="button-link report-comment-open"
                                                data-modal="report-comment-modal-{{ $session['employee_id'] }}"
                                            >
                                                Comment
                                            </button>
                                        @endif
                                    @else
                                        -
                                    @endif
									
									
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">No employee sessions were recorded for {{ $selectedRangeLabel }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($canManageReportComments)
            @foreach ($employeeSessions as $session)
                @if ($session['employee_id'])
                    <div
                        class="report-comment-modal"
                        id="report-comment-modal-{{ $session['employee_id'] }}"
                        hidden
                    >
                        <div class="report-comment-backdrop" data-close-modal="report-comment-modal-{{ $session['employee_id'] }}"></div>
                        <div class="report-comment-dialog">
                            <div class="panel-head">
                                <div>
                                    <p class="eyebrow">Report Comment</p>
                                    <h3>{{ $session['employee'] }}</h3>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('reports.comments.store') }}" class="content-stack">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $session['employee_id'] }}">
                                <input type="hidden" name="preset" value="{{ $selectedPreset }}">
                                <input type="hidden" name="from" value="{{ $selectedFrom }}">
                                <input type="hidden" name="to" value="{{ $selectedTo }}">
                                <label class="form-field">
                                    <span>Comment</span>
                                    <textarea name="comment" rows="5" placeholder="Write your comment here...">{{ $session['comment'] }}</textarea>
                                </label>
                                <div class="topbar-actions">
                                    <button
                                        type="button"
                                        class="button-secondary"
                                        data-close-modal="report-comment-modal-{{ $session['employee_id'] }}"
                                    >
                                        Cancel
                                    </button>
                                    <button type="submit" class="button-primary">Submit Comment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        <div class="layout-two">
            <section class="panel-card">
                <div class="panel-head">
                <div>
                    <p class="eyebrow">Idle Analysis</p>
                    <h3>Highest Idle Time for {{ $selectedRangeLabel }}</h3>
                </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Idle</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($idleLeaders as $employee)
                                <tr>
                                    <td>{{ $employee['name'] }}</td>
                                    <td>{{ $employee['idle'] }}</td>
                                    <td>{{ $employee['active'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">Idle reporting will appear here after activity is recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

          {{--  <section class="panel-card">
                <div class="panel-head">
                <div>
                    <p class="eyebrow">Recent Events</p>
                    <h3>System Startup and Shutdown for {{ $selectedRangeLabel }}</h3>
                </div>
                </div>

                <div class="usage-list">
                    @forelse ($systemEvents as $event)
                        <div class="usage-row">
                            <div>
                                <strong>{{ $event['employee'] }}</strong>
                                <p>{{ $event['device'] }} • {{ $event['event'] }}</p>
                            </div>
                            <span>{{ $event['time'] }}</span>
                        </div>
                    @empty
                        <p class="muted-copy">System events will appear after the agent reports startup and shutdown.</p>
                    @endforelse
                </div>
            </section> --}}
        </div>

		{{--   <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Application Breakdown</p>
                    <h3>Application Usage Totals for {{ $selectedRangeLabel }}</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Application</th>
                            <th>Category</th>
                            <th>Tracked Time</th>
                            <th>Keyboard</th>
                            <th>Mouse</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($appBreakdown as $app)
                            <tr>
                                <td>{{ $app['app'] }}</td>
                                <td>{{ $app['category'] }}</td>
                                <td>{{ $app['duration'] }}</td>
                                <td>{{ $app['keyboard'] }}</td>
                                <td>{{ $app['mouse'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">Application analytics will appear here after tracking data syncs.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>  --}}

		{{--  <div class="layout-two">
            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Website Breakdown</p>
                        <h3>Top Domains for {{ $selectedRangeLabel }}</h3>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Category</th>
                                <th>Tracked Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topWebsites as $site)
                                <tr>
                                    <td>{{ $site['domain'] }}</td>
                                    <td>{{ $site['category'] }}</td>
                                    <td>{{ $site['duration'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">Website analytics will appear here after the browser extension sends browsing data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel-card">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Domain Categories</p>
                        <h3>Website Category Totals</h3>
                    </div>
                </div>

                <div class="usage-list">
                    @forelse ($domainAnalytics as $domain)
                        <div class="usage-row">
                            <div>
                                <strong>{{ $domain['category'] }}</strong>
                                <p>{{ $domain['domains'] }} domains tracked</p>
                            </div>
                            <span>{{ $domain['duration'] }}</span>
                        </div>
                    @empty
                        <p class="muted-copy">Domain category totals will appear after website tracking begins.</p>
                    @endforelse
                </div>
            </section>
        </div> --}}

       {{--   <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Multi-Device View</p>
                    <h3>Devices Per Employee for {{ $selectedRangeLabel }}</h3>
                </div>
            </div>

            <div class="usage-list">
                @forelse ($deviceDistribution as $employee)
                    <div class="usage-row">
                        <div>
                            <strong>{{ $employee['name'] }}</strong>
                            <p>Assigned devices used on this date</p>
                        </div>
                        <span>{{ $employee['devices'] }}</span>
                    </div>
                @empty
                    <p class="muted-copy">Device distribution will appear here as employees record sessions on the selected date.</p>
                @endforelse
            </div>
        </section> --}}

        <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Manual Adjustments</p>
                    <h3>Manual Time Entries for {{ $selectedRangeLabel }}</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Minutes</th>
                            <th>Type</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($manualAdjustments as $entry)
                            <tr>
                                <td>{{ $entry['date'] }}</td>
                                <td>{{ $entry['employee'] }}</td>
                                <td>{{ $entry['minutes'] }}</td>
                                <td>{{ $entry['type'] }}</td>
                                <td>{{ $entry['creator'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">Manual time entries will appear here once managers create them.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{--   <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">IP Audit</p>
                    <h3>Hourly IP Tracking for {{ $selectedRangeLabel }}</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Device</th>
                            <th>IP Address</th>
                            <th>Time</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ipAuditLogs as $log)
                            <tr>
                                <td>{{ $log['employee'] }}</td>
                                <td>{{ $log['device'] }}</td>
                                <td>{{ $log['ip_address'] }}</td>
                                <td>{{ $log['time'] }}</td>
                                <td>{{ $log['source'] }}</td>
                                <td>{{ $log['changed'] ? 'Changed' : 'Same IP' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">IP audit logs will appear here as the desktop agent syncs activity from different networks.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section> --}}
    </section>

    @if ($canManageReportComments)
        <style>
            .report-session-comment {
                margin-top: 0.4rem;
                font-size: 0.82rem;
                line-height: 1.45;
                color: #667085;
                white-space: normal;
            }

            .report-comment-open {
                margin-left: 0.75rem;
            }

            .report-comment-modal[hidden] {
                display: none;
            }

            .report-comment-modal {
                position: fixed;
                inset: 0;
                z-index: 1200;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }

            .report-comment-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
            }

            .report-comment-dialog {
                position: relative;
                z-index: 1;
                width: min(560px, 100%);
                background: #fff;
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 24px 64px rgba(15, 23, 42, 0.18);
            }

            .report-comment-dialog textarea {
                min-height: 140px;
                resize: vertical;
            }
        </style>

        <script>
            (() => {
                document.querySelectorAll('.report-comment-open').forEach((button) => {
                    button.addEventListener('click', () => {
                        const modal = document.getElementById(button.dataset.modal);
                        if (modal) {
                            modal.hidden = false;
                        }
                    });
                });

                document.querySelectorAll('[data-close-modal]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const modal = document.getElementById(button.dataset.closeModal);
                        if (modal) {
                            modal.hidden = true;
                        }
                    });
                });
            })();
        </script>
    @endif
@endsection