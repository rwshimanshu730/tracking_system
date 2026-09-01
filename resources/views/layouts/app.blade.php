<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $webUser = auth()->user();
        $actor = $webUser;
        $isCustomer = false;
        $isAdminOrManager = $webUser && method_exists($webUser, 'hasRole') && $webUser->hasRole('admin', 'manager');
        $autoRefreshEnabled = ! $isCustomer && (request()->routeIs('dashboard')
            || request()->routeIs('employees.index')
            || request()->routeIs('live-monitoring.index'));
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle ?? 'Tracking System' }}</title>
        @if ($autoRefreshEnabled)
            <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
            <meta http-equiv="Pragma" content="no-cache">
            <meta http-equiv="Expires" content="0">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body
        class="panel-shell"
        @if ($autoRefreshEnabled)
            data-auto-refresh="true"
            data-auto-refresh-interval="15000"
            data-auto-refresh-url="{{ url()->current() }}"
        @endif
    >
        <div class="panel-grid">
            <aside class="sidebar">
                <div>
                    <div class="brand-mark">TS</div>
                    <div class="brand-copy">
                        <p class="eyebrow">Employee Tracking</p>
                        <h1>TrackSystem</h1>
                    </div>
                </div>

                <nav class="nav-stack">
                    @unless($isCustomer)
                        <a href="{{ route('dashboard') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('dashboard')])>Dashboard</a>
                    @endunless
                        {{--  <a href="{{ route('employees.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('employees.*')])>Employees</a> --}}
						<a href="{{ route('pm.projects.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('pm.*')])>Project Management</a>
						@if ($isAdminOrManager)
                        <a href="{{ route('customer.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('customer.*')])>Customers</a>
                        <a href="{{ route('users.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('users.*') && request('role') !== 'customer'])>Users</a>
                    @endif
                        
                    @if ($isAdminOrManager)
                        <a href="{{ route('employees.manage') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('employees.manage') || request()->routeIs('employees.create') || request()->routeIs('employees.edit')])>Employees</a>
                        <a href="{{ route('devices.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('devices.*')])>Devices</a>
                        <a href="{{ route('notifications.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('notifications.*')])>Notifications</a>
                        <a href="{{ route('productivity-rules.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('productivity-rules.*')])>Productivity Rules</a>
                        <a href="{{ route('manual-time.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('manual-time.*')])>Manual Time</a>
                    @endif
                    @unless($isCustomer)
                        <a href="{{ route('live-monitoring.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('live-monitoring.*')])>Live Monitoring</a>
                    @endunless
                    <a href="{{ route('reports.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('reports.*')])>Reports</a>
                    
                    @unless($isCustomer)
                        <a href="{{ route('settings.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('settings.*')])>Settings</a>
                    @endunless
                    
                </nav>
                <div class="sidebar-note">
                    <p class="eyebrow">Workspace</p>
                    <p>{{ $actor->name ?? 'Guest' }} â€¢ {{ $webUser->role ?? 'Admin' }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="button-secondary button-full">Logout</button>
                    </form>
                </div>
            </aside>

            <main class="main-panel">
                <header class="topbar">
                    <div>
                        <p class="eyebrow">@if($isCustomer)Customer Workspace @else Admin Workspace @endif</p>
                        <h2>{{ $pageTitle ?? 'Dashboard' }}</h2>
                    </div>
                    <div class="topbar-actions">
                        <div class="pill">Today: {{ now()->format('d M Y') }}</div>
                        @if($isCustomer)
                            <div class="pill">Projects: {{ \App\Models\Project::whereHas('customerMembers', function($q){ $q->where('customers.id', auth('customer')->id()); })->count() }}</div>
                        @else
                            <div class="pill"><a href="{{ route('devices.index') }}">
                                    {{ $onlineDevices ?? 0 }} devices online
                                 </a>
                            </div>
                            <div class="pill"> <a href="{{ route('notifications.index') }}">
                                    {{ $unreadNotifications ?? 0 }} open alerts
                                 </a>
                            </div>
							@if (request()->routeIs('users.index'))
                                <a href="{{ route('users.create') }}" class="button-primary">Add Users</a>
                            @endif
                            @if (request()->routeIs('customer.index'))
                                <a href="{{ route('customer.create') }}" class="button-primary">Add Customer</a>
                            @endif
							 @if (request()->routeIs('employees.manage'))
                                <a href="{{ route('employees.create') }}" class="button-primary">Add Employees</a>
                            @endif
							 @if (request()->routeIs('productivity-rules.index'))
                                <a href="{{ route('productivity-rules.create') }}" class="button-primary">Add Rule</a>
                            @endif
							 @if (request()->routeIs('manual-time.index'))
                                <a href="{{ route('manual-time.create') }}" class="button-primary">Add Entry</a>
                            @endif
							
                            @if (request()->routeIs('reports.*') && isset($selectedPreset, $selectedFrom, $selectedTo))
                                <details class="topbar-dropdown">
                                    <summary class="button-secondary">Download</summary>
                                    <div class="topbar-dropdown-menu">
                                        <a href="{{ route('reports.export.daily', ['preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}" class="topbar-dropdown-link">Daily CSV</a>
                                        <a href="{{ route('reports.export.daily-json', ['preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}" class="topbar-dropdown-link">Daily JSON</a>
                                        <a href="{{ route('reports.export.apps', ['preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}" class="topbar-dropdown-link">App Usage CSV</a>
                                        <a href="{{ route('reports.export.manual-time', ['preset' => $selectedPreset, 'from' => $selectedFrom, 'to' => $selectedTo]) }}" class="topbar-dropdown-link">Manual Time CSV</a>
                                    </div>
                                </details>
                            @endif
                        @endif
						
                    </div>
					
					
                </header>

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </body>
</html>

