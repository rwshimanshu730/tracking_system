@extends('layouts.app')

@section('content')
    <section class="content-stack">
        <div class="section-banner">
            <div>
                <p class="eyebrow">Device Form</p>
                <h3>{{ $pageTitle }}</h3>
            </div>
        </div>

        <section class="panel-card">
            <form method="POST" action="{{ route('devices.update', $device) }}" class="form-grid">
                @csrf
                @method('PUT')

                <label class="form-field">
                    <span>Device Name</span>
                    <input type="text" name="device_name" value="{{ old('device_name', $device->device_name) }}" required>
                </label>

                <label class="form-field">
                    <span>Machine Name</span>
                    <input type="text" value="{{ $device->machine_name }}" disabled>
                </label>

                <label class="form-field">
                    <span>Employee</span>
                    <select name="employee_id">
                        <option value="">Unassigned</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('employee_id', $device->employee_id) === (string) $employee->id)>{{ $employee->name }} ({{ $employee->employee_code }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>OS Name</span>
                    <input type="text" name="os_name" value="{{ old('os_name', $device->os_name) }}">
                </label>

                <label class="form-field">
                    <span>Agent Version</span>
                    <input type="text" name="agent_version" value="{{ old('agent_version', $device->agent_version) }}">
                </label>

                <label class="form-check">
                    <input type="checkbox" name="is_online" value="1" @checked(old('is_online', $device->is_online))>
                    <span>Mark as online</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save Device</button>
                    <a href="{{ route('devices.index') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>

        <section class="panel-card">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Device History</p>
                    <h3>Recent Sessions</h3>
                </div>
            </div>

            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Ended</th>
                            <th>Active</th>
                            <th>Idle</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($device->workSessions as $session)
                            <tr>
                                <td>{{ $session->started_at?->format('d M Y H:i') }}</td>
                                <td>{{ $session->ended_at?->format('d M Y H:i') ?? 'Open' }}</td>
                                <td>{{ $session->active_seconds }}s</td>
                                <td>{{ $session->idle_seconds }}s</td>
                                <td>{{ ucfirst($session->status) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No session history for this device yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
