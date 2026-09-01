<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Customer Login</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell">
        <main class="auth-card">
            <div>
                <p class="eyebrow">Customer Sign In</p>
                <h1 class="auth-title">TrackSystem</h1>
                <p class="muted-copy">Sign in to view your projects and updates.</p>
            </div>

            @if (session('status'))
                <div class="notice-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('customer.login.store') }}" class="form-stack">
                @csrf
                <label class="form-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<small>{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Password</span>
                    <input type="password" name="password" required>
                </label>

                <label class="form-check">
                    <input type="checkbox" name="remember" value="1">
                    <span>Keep me signed in</span>
                </label>

                <button type="submit" class="button-primary">Sign In</button>
            </form>

           {{--   <p style="margin-top:16px; text-align:center;">
                Don't have an account? <a href="{{ route('customer.register') }}">Register</a>
            </p> --}}
        </main>
    </body>
</html>
