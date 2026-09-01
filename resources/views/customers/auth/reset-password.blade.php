@extends('layouts.auth-clean')

@section('content')
    <section class="content-stack">
        <div>
            <div>
                <p class="eyebrow">Customer Access</p>
                <h1 class="auth-title">Reset Password</h1>
                <p class="muted-copy">Set a new password to access your projects and updates.</p>
            </div>
        </div>

        <section>
            <form method="POST" action="{{ route('customer.password.update') }}" class="form-grid">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>New Password</span>
                    <input type="password" name="password" required>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" required>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Reset Password</button>
                    <a href="{{ route('customer.login') }}" class="button-secondary">Back to Login</a>
                </div>
            </form>
        </section>
    </section>
@endsection
