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
                            <th>Code</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Devices</th>
                            <th>Actions</th>
							<th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employee->employee_code }}</td>
                                <td>{{ $employee->name }}</td>
                                <td>{{ $employee->department ?: 'Unassigned' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $employee->employment_status)) }}</td>
                                <td>{{ $employee->devices_count }}</td>
                                <td class="action-cell">
                                    <a href="{{ route('employees.edit', $employee) }}">Edit</a>
                                    <form method="POST" action="{{ route('employees.destroy', $employee) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button-link" onclick="return confirm('Delete this employee?')">Delete</button>
                                    </form>
                                </td>
								<td><a href="{{ route('employees.quick-login', $employee) }}">Login</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No employees found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
