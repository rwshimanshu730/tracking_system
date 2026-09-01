<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $customerUser = auth('customer')->user();
        $assignedProjects = $customerUser
            ? \App\Models\Project::whereHas('customerMembers', fn ($q) => $q->where('customers.id', $customerUser->id))->count()
            : 0;
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle ?? 'Customer Portal' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="panel-shell">
        <div class="panel-grid">
            <aside class="sidebar">
                <div>
                    <div class="brand-mark">TS</div>
                    <div class="brand-copy">
                        <p class="eyebrow">Customer Portal</p>
                        <h1>TrackSystem</h1>
                    </div>
                </div>

                <nav class="nav-stack">
			<!--	 <a href="{{ route('customer.reports.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('customer.reports.*')])>Reports</a> -->
                    <a href="{{ route('customer.projects.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('customer.projects.*')])>Project Management</a>
                </nav>

                <div class="sidebar-note">
                    <p class="eyebrow">Workspace</p>
                    <p>{{ $customerUser->name ?? 'Customer' }} • Customer</p>
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="button-secondary button-full">Logout</button>
                    </form>
                </div>
            </aside>

            <main class="main-panel">
                <header class="topbar">
                    <div>
                        <p class="eyebrow">Customer Workspace</p>
                        <h2>{{ $pageTitle ?? 'Customer Portal' }}</h2>
                    </div>
                    <div class="topbar-actions">
                        <div class="pill">Today: {{ now()->format('d M Y') }}</div>
                        <div class="pill">Projects: {{ $assignedProjects }}</div>
                    </div>
                </header>

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </body>
</html>
