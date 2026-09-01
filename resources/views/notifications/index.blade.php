@extends('layouts.app')

@section('content')
    <section class="content-stack">
        
        
        @if (session('status'))
            <div class="notice-success">{{ session('status') }}</div>
        @endif

        <section class="panel-card">
            <div class="table-shell">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Raised</th>
                            <th>Type</th>
                            <th>Employee</th>
                            <th>Device</th>
                            <th>Message</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($notifications as $notification)
                            <tr>
                                <td>{{ $notification->raised_at?->diffForHumans() }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $notification->type)) }}</td>
                                <td>{{ $notification->employee?->name ?? 'Unassigned' }}</td>
                                <td>{{ $notification->device?->device_name ?? 'Unknown Device' }}</td>
                                <td>{{ $notification->message }}</td>
                                <td class="action-cell">
                                    @if ($notification->is_read)
                                        <span class="status-dot">Read</span>
                                    @else
                                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                            @csrf
                                            <button type="submit" class="button-link">Mark Read</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No alerts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
