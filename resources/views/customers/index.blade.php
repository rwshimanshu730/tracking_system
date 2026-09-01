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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
							<th>Mail</th>
							<th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>Customer</td>
                                <td class="action-cell">
                                    <a href="{{ route('customer.edit', $user) }}">Edit</a>
                                    <form method="POST" action="{{ route('customer.destroy', $user) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button-link" onclick="return confirm('Delete this Customer?')">Delete</button>
                                    </form>
                                </td>
								<td>
                                    <form method="POST" action="{{ route('customer.send-reset-password', $user) }}">
                                        @csrf
                                        <button type="submit" class="button-link" title="Send reset password email" aria-label="Send reset password email">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M4 6H20V18H4V6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                <path d="M4 7L12 13L20 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
								<td><a href="{{ route('customer.quick-login', $user) }}">Login</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </section>
@endsection
