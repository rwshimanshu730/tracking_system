<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Customer Register</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell">
        <main class="auth-card">
            <div>
                <p class="eyebrow">Customer Sign Up</p>
                <h1 class="auth-title">TrackSystem</h1>
                <p class="muted-copy">Create an account to view projects and updates.</p>
            </div>

            @if (session('status'))
                <div class="notice-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('customer.register.store') }}" class="form-stack">
                @csrf

                <label class="form-field">
                    <span>Name</span>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Password</span>
                    <input type="password" name="password" required>
                    @error('password')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" required>
                </label>

                <div class="form-actions">
                    <button type="submit" class="button-primary">Create Account</button>
                </div>
            </form>

            <p style="margin-top:16px; text-align:center;">
                Already have an account? <a href="{{ route('customer.login') }}">Sign in</a>
            </p>
        </main>
    </body>
</html>
