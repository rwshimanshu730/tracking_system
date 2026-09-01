<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $employeeUser = auth('employee')->user();
        $employeeRecord = null;

        if ($employeeUser instanceof \App\Models\Employee) {
            $employeeRecord = $employeeUser;
        }

        $assignedProjects = $employeeRecord
            ? \App\Models\Project::whereHas('employeeMembers', fn ($q) => $q->where('employees.id', $employeeRecord->id))->count()
            : 0;
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle ?? 'Employee Portal' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="panel-shell">
        <div class="panel-grid">
            <aside class="sidebar">
                <div>
                    <div class="brand-mark">TS</div>
                    <div class="brand-copy">
                        <p class="eyebrow">Employee Portal</p>
                        <h1>TrackSystem</h1>
                    </div>
                </div>

                <nav class="nav-stack">
                    <a href="{{ route('employee.reports.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('employee.reports.*')])>Reports</a>
                    <a href="{{ route('employee.projects.index') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('employee.projects.*')])>Project Management</a>
                </nav>

                <div class="sidebar-note">
                    <p class="eyebrow">Workspace</p>
                    <p>
                        {{ $employeeRecord?->employee_code ?: ($employeeRecord?->name ?? $employeeUser->name ?? 'Employee') }}
                        • Employee
                    </p>
                    <form method="POST" action="{{ route('employee.logout') }}">
                        @csrf
                        <button type="submit" class="button-secondary button-full">Logout</button>
                    </form>
                </div>
            </aside>

            <main class="main-panel">
                <header class="topbar">
                    <div>
                        <p class="eyebrow">Employee Workspace</p>
                        <h2>{{ $pageTitle ?? 'Employee Portal' }}</h2>
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
