<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Employee Login</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell">
        <main class="auth-card">
            <div>
                <p class="eyebrow">Employee Sign In</p>
                <h1 class="auth-title">TrackSystem</h1>
                <p class="muted-copy">Sign in to view your assigned projects and tasks.</p>
                <p class="muted-copy">Use your Employee Code (e.g., E1234) or your registered email to sign in.</p>
            </div>

            @if (session('status'))
                <div class="notice-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('employee.login.store') }}" class="form-stack">
                @csrf
                <label class="form-field">
                    <span>Employee Code</span>
                    <input type="text" name="employee_code" value="{{ old('employee_code') }}">
                    @error('employee_code')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Password</span>
                    <input type="password" name="password" value="{{ old('password') }}">
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                {{-- Passwordless login: no password required --}}

                <button type="submit" class="button-primary">Sign In</button>
            </form>

        </main>
    </body>
</html>
