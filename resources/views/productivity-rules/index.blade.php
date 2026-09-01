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
                            <th>Match Type</th>
                            <th>Match Value</th>
                            <th>Category</th>
                            <th>Productivity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td>{{ $rule->match_type }}</td>
                                <td>{{ $rule->match_value }}</td>
                                <td>{{ $rule->category }}</td>
                                <td>{{ ucfirst($rule->productivity_type) }}</td>
                                <td>{{ $rule->is_active ? 'Active' : 'Disabled' }}</td>
                                <td class="action-cell">
                                    <a href="{{ route('productivity-rules.edit', $rule) }}">Edit</a>
                                    <form method="POST" action="{{ route('productivity-rules.destroy', $rule) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button-link" onclick="return confirm('Delete this rule?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No productivity rules defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
