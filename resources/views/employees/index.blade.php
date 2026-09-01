@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Directory</p>
                <h3>Employees and assigned devices</h3>
            </div>
            <div class="pill">{{ $employees->count() }} registered staff</div>
        </div>

        <section class="panel-card">
            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Primary Device</th>
                            <th>Devices</th>
                            <th>Last Seen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employee['name'] }}</td>
                                <td>{{ $employee['department'] }}</td>
                                <td>{{ $employee['device'] }}</td>
                                <td>{{ $employee['deviceCount'] }}</td>
                                <td>{{ $employee['lastSeen'] }}</td>
                                <td><span class="status-dot">{{ $employee['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No employees are registered yet. Device registration from the agent will create the first records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
