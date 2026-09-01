<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Setup Admin</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell">
        <main class="auth-card">
            <div>
                <p class="eyebrow">First-Time Setup</p>
                <h1 class="auth-title">Create Admin Account</h1>
                <p class="muted-copy">This runs only once and creates the first admin user for the tracking system.</p>
            </div>

            <form method="POST" action="{{ route('setup.store') }}" class="form-stack">
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

                <button type="submit" class="button-primary">Create Admin</button>
            </form>
        </main>
    </body>
</html>
