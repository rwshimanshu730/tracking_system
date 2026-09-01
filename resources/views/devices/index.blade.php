@extends('layouts.app')

@section('content')
    <section class="content-stack">
        
          <div>{{ $devices->total() }} registered devices</div>
      

        @if (session('status'))
            <div class="notice-success">{{ session('status') }}</div>
        @endif

        <section class="panel-card">
            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Machine</th>
                            <th>Employee</th>
                            <th>Sessions</th>
                            <th>Last Seen</th>
                            <th>Status</th>
                            <th>Activation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td>{{ $device->device_name }}</td>
                                <td>{{ $device->machine_name }}</td>
                                <td>{{ $device->employee?->name ?? 'Unassigned' }}</td>
                                <td>{{ $device->work_sessions_count }}</td>
                                <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>{{ $device->is_online ? 'Online' : 'Offline' }}</td>
                                <td>{{ $device->api_token ? 'Activated' : 'Pending first run' }}</td>
                                <td class="action-cell">
                                    <a href="{{ route('devices.edit', $device) }}">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No devices registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
