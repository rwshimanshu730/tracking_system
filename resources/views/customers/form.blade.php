@extends('layouts.app')

@section('content')
    <section class="content-stack">
	

        <section class="panel-card">
            <form method="POST" action="{{ $formAction }}" class="form-grid">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <label class="form-field">
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Role</span>
                    <select name="role">
                        @foreach (['admin' => 'Admin', 'manager' => 'Manager', 'user' => 'Employee', 'customer' => 'Customer'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role ?: 'user') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Password</span>
                    <input type="password" name="password" {{ $formMethod === 'POST' ? 'required' : '' }}>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" {{ $formMethod === 'POST' ? 'required' : '' }}>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Save customer</button>
                    <a href="{{ route('customer.index') }}" class="button-secondary">Back</a>
                </div>
            </form>
        </section>
    </section>
@endsection
