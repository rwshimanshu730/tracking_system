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
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Minutes</th>
                            <th>Reason</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date?->format('d M Y') }}</td>
                                <td>{{ $entry->employee?->name }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $entry->entry_type)) }}</td>
                                <td>{{ $entry->minutes }}</td>
                                <td>{{ $entry->reason ?: '-' }}</td>
                                <td>{{ $entry->creator?->name }}</td>
                                <td class="action-cell">
                                    <a href="{{ route('manual-time.edit', $entry) }}">Edit</a>
                                    <form method="POST" action="{{ route('manual-time.destroy', $entry) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button-link" onclick="return confirm('Delete this entry?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No manual time entries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
