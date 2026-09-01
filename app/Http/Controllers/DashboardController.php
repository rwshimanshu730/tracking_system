<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceIpLog;
use App\Models\Employee;
use App\Models\ManualTimeEntry;
use App\Models\Notification;
use App\Models\ProductivityRule;
use App\Models\ReportComment;
use App\Models\SystemEvent;
use App\Models\WebsiteLog;
use App\Models\WorkSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectBug;
use App\Models\ProjectFile;

class DashboardController extends Controller
{
    public function index(): View
    {
        $this->closeStaleSessions();
        [$todayStart, $todayEnd] = $this->todayRange();
        $totalEmployees = Employee::count();
        $presentToday = WorkSession::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->distinct('employee_id')
            ->count('employee_id');
        $onlineDevices = Device::where('is_online', true)
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->count();
        $currentSessionProductive = ActivityLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('activity_type', '!=', 'idle')
            ->whereHas('workSession', fn ($query) => $query->whereNull('ended_at'))
            ->sum('duration_seconds');
        $currentSessionIdle = ActivityLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('activity_type', 'idle')
            ->whereHas('workSession', fn ($query) => $query->whereNull('ended_at'))
            ->sum('duration_seconds');
        $todayProductivity = ActivityLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('activity_type', '!=', 'idle')
            ->sum('duration_seconds');
        $todayIdle = ActivityLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('activity_type', 'idle')
            ->sum('duration_seconds');
        $manualMinutes = ManualTimeEntry::query()
            ->whereDate('entry_date', '>=', $todayStart->toDateString())
            ->whereDate('entry_date', '<=', $todayEnd->toDateString())
            ->sum('minutes');

        $topEmployees = Employee::query()
            ->leftJoin('activity_logs', function ($join) use ($todayStart, $todayEnd) {
                $join->on('employees.id', '=', 'activity_logs.employee_id')
                    ->whereBetween('activity_logs.started_at', [$todayStart, $todayEnd]);
            })
            ->select(
                'employees.name',
                'employees.department',
                DB::raw("COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN 0 ELSE activity_logs.duration_seconds END), 0) as productive_seconds"),
                DB::raw("COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN activity_logs.duration_seconds ELSE 0 END), 0) as idle_seconds"),
                DB::raw("CASE
                    WHEN COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN activity_logs.duration_seconds ELSE 0 END), 0) >= 1800 THEN 'Idle'
                    WHEN COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN 0 ELSE activity_logs.duration_seconds END), 0) > 0 THEN 'Active'
                    ELSE 'Offline'
                END as current_status")
            )
            ->groupBy('employees.id', 'employees.name', 'employees.department')
            ->orderByDesc('productive_seconds')
            ->limit(5)
            ->get()
            ->map(fn ($employee) => [
                'name' => $employee->name,
                'department' => $employee->department ?: 'Unassigned',
                'productive' => $this->formatDuration((int) $employee->productive_seconds),
                'idle' => $this->formatDuration((int) $employee->idle_seconds),
                'status' => $employee->current_status,
            ]);

        $appUsageTotals = ActivityLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->select('app_name', 'category', DB::raw('SUM(duration_seconds) as total_seconds'))
            ->groupBy('app_name', 'category')
            ->orderByDesc('total_seconds')
            ->limit(8)
            ->get();

        $totalUsageSeconds = max(1, (int) $appUsageTotals->sum('total_seconds'));
        $appUsage = $appUsageTotals->map(fn ($app) => [
            'app' => $app->app_name,
            'category' => $app->category ?: 'General',
            'duration' => $this->formatDuration((int) $app->total_seconds),
            'share' => round(((int) $app->total_seconds / $totalUsageSeconds) * 100).'%',
        ]);

        $websiteTotals = WebsiteLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->select('domain', DB::raw('SUM(duration_seconds) as total_seconds'))
            ->groupBy('domain')
            ->orderByDesc('total_seconds')
            ->limit(8)
            ->get();

        $totalWebsiteSeconds = max(1, (int) $websiteTotals->sum('total_seconds'));
        $websiteUsage = $websiteTotals->map(fn ($site) => [
            'domain' => $site->domain,
            'duration' => $this->formatDuration((int) $site->total_seconds),
            'share' => round(((int) $site->total_seconds / $totalWebsiteSeconds) * 100).'%',
        ]);

        $todayTimeline = collect(range(0, 4))->map(function (int $offset) use ($todayStart) {
            $slotStart = $todayStart->copy()->addHours(9 + ($offset * 2));
            $slotEnd = $slotStart->copy()->addHours(2);

            $active = ActivityLog::whereBetween('started_at', [$slotStart, $slotEnd])
                ->where('activity_type', '!=', 'idle')
                ->distinct('employee_id')
                ->count('employee_id');

            $idleCount = ActivityLog::whereBetween('started_at', [$slotStart, $slotEnd])
                ->where('activity_type', 'idle')
                ->distinct('employee_id')
                ->count('employee_id');

            return [
                'hour' => $slotStart->format('H:i'),
                'active' => $active,
                'idle' => $idleCount,
            ];
        });

        $teamSnapshots = Employee::query()
            ->leftJoin('activity_logs', function ($join) use ($todayStart, $todayEnd) {
                $join->on('employees.id', '=', 'activity_logs.employee_id')
                    ->whereBetween('activity_logs.started_at', [$todayStart, $todayEnd]);
            })
            ->select(
                'employees.department',
                DB::raw('COUNT(DISTINCT employees.id) as employee_count'),
                DB::raw("COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN 0 ELSE activity_logs.duration_seconds END), 0) as active_seconds"),
                DB::raw("COALESCE(SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN activity_logs.duration_seconds ELSE 0 END), 0) as idle_seconds")
            )
            ->whereNotNull('employees.department')
            ->groupBy('employees.department')
            ->orderByDesc('employee_count')
            ->limit(3)
            ->get()
            ->map(function ($team) {
                $total = max(1, (int) $team->active_seconds + (int) $team->idle_seconds);
                return [
                    'name' => $team->department,
                    'active' => $team->employee_count.' employees',
                    'efficiency' => round(((int) $team->active_seconds / $total) * 100).'%',
                ];
            });

        return view('dashboard', [
            'pageTitle' => 'Operations Dashboard',
            'summaryCards' => [
                ['label' => 'Total Employees', 'value' => (string) $totalEmployees, 'trend' => 'Registered staff'],
                ['label' => 'Present Today', 'value' => (string) $presentToday, 'trend' => 'Employees with a work session today'],
                ['label' => 'Current Session Productive', 'value' => $this->formatDuration((int) $currentSessionProductive), 'trend' => 'Active time across open sessions'],
                ['label' => 'Current Session Idle', 'value' => $this->formatDuration((int) $currentSessionIdle), 'trend' => 'Idle time across open sessions'],
                ['label' => 'Today Productive Total', 'value' => $this->formatDuration((int) $todayProductivity), 'trend' => 'All active time tracked today'],
                ['label' => 'Today Idle Total', 'value' => $this->formatDuration((int) $todayIdle), 'trend' => 'All idle time tracked today'],
                ['label' => 'Manual Time', 'value' => $this->formatDuration($manualMinutes * 60), 'trend' => 'Manager adjustments added today'],
            ],
            'teamSnapshots' => $teamSnapshots,
            'todayTimeline' => $todayTimeline,
            'topEmployees' => $topEmployees,
            'appUsage' => $appUsage,
            'websiteUsage' => $websiteUsage,
            'onlineDevices' => $onlineDevices,
            'unreadNotifications' => Notification::where('is_read', false)->count(),
        ]);
    }

    public function employees(): View
    {
        $employees = Employee::query()
            ->with(['devices' => fn ($query) => $query->latest('last_seen_at')])
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee) {
                $device = $employee->devices->first();
                $lastSeen = $device?->last_seen_at;

                return [
                    'name' => $employee->name,
                    'department' => $employee->department ?: 'Unassigned',
                    'device' => $device?->device_name ?? 'No device',
                    'deviceCount' => $employee->devices->count(),
                    'lastSeen' => $lastSeen ? $lastSeen->diffForHumans() : 'Never',
                    'status' => $this->deviceStatus($device?->is_online ?? false, $lastSeen),
                ];
            });

        return view('employees.index', [
            'pageTitle' => 'Employees',
            'employees' => $employees,
        ]);
    }

    public function liveMonitoring(): View
    {
        $this->closeStaleSessions();
        [$todayStart, $todayEnd] = $this->todayRange();

        $liveEmployees = Employee::query()
            ->with([
                'devices' => fn ($query) => $query->latest('last_seen_at'),
                'activityLogs' => fn ($query) => $query->with('device')->latest('started_at')->limit(1),
                'workSessions' => fn ($query) => $query->whereBetween('started_at', [$todayStart, $todayEnd])->latest('started_at'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee) use ($todayStart, $todayEnd) {
                $latestLog = $employee->activityLogs->first();
                $latestDevice = $latestLog?->device ?? $employee->devices->first();
                $activeSeconds = (int) ActivityLog::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('started_at', [$todayStart, $todayEnd])
                    ->where('activity_type', '!=', 'idle')
                    ->sum('duration_seconds');
                $idleSeconds = (int) ActivityLog::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('started_at', [$todayStart, $todayEnd])
                    ->where('activity_type', 'idle')
                    ->sum('duration_seconds');
                $lastActivityAt = $latestLog?->ended_at ?? $latestLog?->started_at ?? $latestDevice?->last_seen_at;

                return [
                    'employee' => $employee,
                    'name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'device_name' => $latestDevice?->device_name ?? 'No device',
                    'machine_name' => $latestDevice?->machine_name ?? '-',
                    'app' => $latestLog?->app_name ?? 'Waiting for activity',
                    'window' => $latestLog?->window_title ?: 'No active window',
                    'status' => $this->liveStatus($latestDevice?->is_online ?? false, $lastActivityAt, $latestLog?->activity_type),
                    'last_active' => $lastActivityAt?->diffForHumans() ?? 'Never',
                    'active' => $this->formatDuration($activeSeconds),
                    'idle' => $this->formatDuration($idleSeconds),
                ];
            });

        return view('live-monitoring.index', [
            'pageTitle' => 'Live Monitoring',
            'liveEmployees' => $liveEmployees,
            'onlineDevices' => Device::where('is_online', true)->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'unreadNotifications' => Notification::where('is_read', false)->count(),
        ]);
    }

    public function liveMonitoringEmployee(Employee $employee): View
    {
        $this->closeStaleSessions();
        [$today, $todayEnd] = $this->todayRange();
        $todaySessions = WorkSession::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [
                $today,
                $todayEnd,
            ])
            ->latest('started_at')
            ->get();

        $recentActivity = ActivityLog::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->limit(12)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'app' => $log->app_name,
                'window' => $log->window_title ?: 'Untitled Window',
                'device' => $log->device?->device_name ?? 'Unknown Device',
                'duration' => $this->formatDuration($log->duration_seconds),
                'state' => $log->activity_type === 'idle' ? 'Idle' : 'Active',
                'time' => ($log->ended_at ?? $log->started_at)?->timezone('Asia/Kolkata')->format('d M Y H:i') ?? '-',
            ]);

        $websiteActivity = WebsiteLog::query()
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (WebsiteLog $site) => [
                'domain' => $site->domain,
                'title' => $site->page_title ?: $site->url,
                'browser' => $site->browser_name,
                'duration' => $this->formatDuration($site->duration_seconds),
                'time' => ($site->ended_at ?? $site->started_at)?->timezone('Asia/Kolkata')->format('d M Y H:i') ?? '-',
            ]);

        $systemEvents = SystemEvent::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (SystemEvent $event) => [
                'device' => $event->device?->device_name ?? 'Unknown Device',
                'event' => str_replace('_', ' ', ucfirst($event->event_type)),
                'time' => $event->occurred_at->timezone('Asia/Kolkata')->format('d M Y H:i'),
            ]);

        $latestActivity = ActivityLog::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->first();
        $latestDevice = $latestActivity?->device ?? $employee->devices()->latest('last_seen_at')->first();
     $activeSeconds = (int) \App\Models\ActivityLog::query()
    ->where('employee_id', $employee->id)
    ->whereBetween('started_at', [$today, $todayEnd])
    ->where('activity_type', '!=', 'idle')
    ->sum('duration_seconds');
        $idleSeconds = (int) \App\Models\ActivityLog::query()
    ->where('employee_id', $employee->id)
    ->whereBetween('started_at', [$today, $todayEnd])
    ->where('activity_type', 'idle')
    ->sum('duration_seconds');
        $sortedSessions = $todaySessions->sortBy('login_at')->values();
        $firstSession = $sortedSessions->first();
        $hasOpenSession = $todaySessions->contains(fn ($session) => $session->ended_at === null);
        $firstNonIdleActivity = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$today, $todayEnd])
            ->where('activity_type', '!=', 'idle')
            ->orderBy('started_at')
            ->first();
        $firstAnyActivity = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$today, $todayEnd])
            ->orderBy('started_at')
            ->first();
        $loginAt = $firstNonIdleActivity?->started_at
            ?? $firstAnyActivity?->started_at
            ?? $firstSession?->login_at;
        $latestEndedAt = $sortedSessions
            ->pluck('ended_at')
            ->filter()
            ->sort()
            ->last();
        $logoutLabel = $sortedSessions->isEmpty()
            ? '-'
            : ($hasOpenSession
            ? 'Open'
            : ($latestEndedAt
                ? Carbon::parse($latestEndedAt)->timezone('Asia/Kolkata')->format('H:i')
                : '-'));

        $timeline = collect(range(0, 4))->map(function (int $offset) use ($today, $employee) {
            $slotStart = $today->copy()->addHours(9 + ($offset * 2));
            $slotEnd = $slotStart->copy()->addHours(2);

            $activeSeconds = (int) ActivityLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('started_at', [$slotStart, $slotEnd])
                ->where('activity_type', '!=', 'idle')
                ->sum('duration_seconds');

            $idleSeconds = (int) ActivityLog::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('started_at', [$slotStart, $slotEnd])
                ->where('activity_type', 'idle')
                ->sum('duration_seconds');

            return [
                'hour' => $slotStart->format('H:i'),
                'active' => $this->formatDuration($activeSeconds),
                'idle' => $this->formatDuration($idleSeconds),
            ];
        });

        return view('live-monitoring.index', [
            'pageTitle' => $employee->name.' Live Monitoring',
            'employeeDetail' => [
                'employee' => $employee,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department ?: 'Unassigned',
                'device_name' => $latestDevice?->device_name ?? 'No device',
                'machine_name' => $latestDevice?->machine_name ?? '-',
                'status' => $this->liveStatus($latestDevice?->is_online ?? false, $latestActivity?->ended_at ?? $latestActivity?->started_at ?? $latestDevice?->last_seen_at, $latestActivity?->activity_type),
                'login' => $loginAt?->timezone('Asia/Kolkata')->format('H:i') ?? '-',
                'logout' => $logoutLabel,
                'active' => $this->formatDuration($activeSeconds),
                'idle' => $this->formatDuration($idleSeconds),
                'current_app' => $latestActivity?->app_name ?? 'Waiting for activity',
                'current_window' => $latestActivity?->window_title ?: 'No active window',
                'last_active' => ($latestActivity?->ended_at ?? $latestActivity?->started_at ?? $latestDevice?->last_seen_at)?->diffForHumans() ?? 'Never',
                'timeline' => $timeline,
            ],
            'recentActivity' => $recentActivity,
            'websiteActivity' => $websiteActivity,
            'systemEvents' => $systemEvents,
            'onlineDevices' => Device::where('is_online', true)->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'unreadNotifications' => Notification::where('is_read', false)->count(),
        ]);
    }

    public function reports(Request $request): View
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd, $selectedRangeLabel, $selectedPreset, $singleDay, $selectedFrom, $selectedTo] = $this->resolveReportRange($request);

        // If the current user is a customer, show project-focused reports instead
        $user = auth()->user() ?? auth('employee')->user() ?? auth('customer')->user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('customer')) {
            $baseQuery = Project::query()->whereHas('customerMembers', fn ($q) => $q->where('customers.id', $user->id));

            $projects = (clone $baseQuery)->withCount(['tasks', 'bugs', 'files'])->get();
            $projectIds = $projects->pluck('id')->all();

            if (empty($projectIds)) {
                $projectReports = [
                    'total_projects' => 0,
                    'total_tasks' => 0,
                    'tasks_completed' => 0,
                    'total_bugs' => 0,
                    'total_files' => 0,
                    'avg_progress' => 0,
                    'projects' => [],
                ];
            } else {
                $totalTasks = ProjectTask::whereIn('project_id', $projectIds)->count();
                $tasksCompleted = ProjectTask::whereIn('project_id', $projectIds)->where('progress', '>=', 100)->count();
                $totalBugs = ProjectBug::whereIn('project_id', $projectIds)->count();
                $totalFiles = ProjectFile::whereIn('project_id', $projectIds)->count();
                $avgProgress = (int) round((float) ProjectTask::whereIn('project_id', $projectIds)->avg('progress') ?? 0);

                $projectsList = $projects->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'tasks' => $p->tasks_count ?? 0,
                    'bugs' => $p->bugs_count ?? 0,
                    'files' => $p->files_count ?? 0,
                    'avg_progress' => (int) round((float) ProjectTask::where('project_id', $p->id)->avg('progress') ?? 0),
                ])->all();

                $projectReports = [
                    'total_projects' => count($projectIds),
                    'total_tasks' => $totalTasks,
                    'tasks_completed' => $tasksCompleted,
                    'total_bugs' => $totalBugs,
                    'total_files' => $totalFiles,
                    'avg_progress' => $avgProgress,
                    'projects' => $projectsList,
                ];
            }

            return view('reports.customer', [
                'pageTitle' => 'Project Reports',
                'selectedRangeLabel' => $selectedRangeLabel,
                'selectedPreset' => $selectedPreset,
                'selectedFrom' => $selectedFrom,
                'selectedTo' => $selectedTo,
                'projectReports' => $projectReports,
                'projects' => $projects,
                'unreadNotifications' => Notification::where('is_read', false)->count(),
            ]);
        }

        $reportCards = [
            [
                'title' => 'Selected Range',
                'detail' => $selectedRangeLabel.' is the active report range.',
            ],
            [
                'title' => 'Tracked Active Time',
                'detail' => $this->formatDuration((int) ActivityLog::query()
                    ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                    ->where('activity_type', '!=', 'idle')
                    ->sum('duration_seconds')).' active time recorded in this range.',
            ],
            [
                'title' => 'Tracked Idle Time',
                'detail' => $this->formatDuration((int) ActivityLog::query()
                    ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                    ->where('activity_type', 'idle')
                    ->sum('duration_seconds')).' idle time recorded in this range.',
            ],
            [
                'title' => 'Timezone',
                'detail' => config('app.timezone').' is the active reporting timezone.',
            ],
            [
                'title' => 'IP Change Alerts',
                'detail' => DeviceIpLog::query()
                    ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
                    ->where('is_changed', true)
                    ->count().' IP changes recorded in this range.',
            ],
        ];

        $idleLeaders = Employee::query()
            ->join('activity_logs', 'employees.id', '=', 'activity_logs.employee_id')
            ->whereBetween('activity_logs.started_at', [$rangeStart, $rangeEnd])
            ->select(
                'employees.name',
                DB::raw("SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN activity_logs.duration_seconds ELSE 0 END) as idle_seconds"),
                DB::raw("SUM(CASE WHEN activity_logs.activity_type = 'idle' THEN 0 ELSE activity_logs.duration_seconds END) as active_seconds")
            )
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('idle_seconds')
            ->limit(5)
            ->get()
            ->map(fn ($employee) => [
                'name' => $employee->name,
                'idle' => $this->formatDuration((int) $employee->idle_seconds),
                'active' => $this->formatDuration((int) $employee->active_seconds),
            ]);

        $manualAdjustments = ManualTimeEntry::with(['employee', 'creator'])
            ->whereDate('entry_date', '>=', $rangeStart->toDateString())
            ->whereDate('entry_date', '<=', $rangeEnd->toDateString())
            ->latest('entry_date')
            ->limit(10)
            ->get()
            ->map(fn ($entry) => [
                'date' => $entry->entry_date?->format('d M Y'),
                'employee' => $entry->employee?->name ?? 'Unknown',
                'minutes' => $entry->minutes,
                'type' => ucfirst(str_replace('_', ' ', $entry->entry_type)),
                'creator' => $entry->creator?->name ?? 'Unknown',
            ]);

        $appBreakdown = ActivityLog::query()
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->select('app_name', 'category', DB::raw('SUM(duration_seconds) as total_seconds'), DB::raw('SUM(keyboard_events) as total_keyboard_events'), DB::raw('SUM(mouse_events) as total_mouse_events'))
            ->groupBy('app_name', 'category')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn ($app) => [
                'app' => $app->app_name,
                'category' => $app->category ?: 'General',
                'duration' => $this->formatDuration((int) $app->total_seconds),
                'keyboard' => (int) $app->total_keyboard_events,
                'mouse' => (int) $app->total_mouse_events,
            ]);

        $systemEvents = SystemEvent::query()
            ->with('employee', 'device')
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (SystemEvent $event) => [
                'employee' => $event->employee?->name ?? 'Unknown Employee',
                'device' => $event->device?->device_name ?? 'Unknown Device',
                'event' => str_replace('_', ' ', ucfirst($event->event_type)),
                'time' => $this->formatDateTime($event->occurred_at, false),
            ]);

        $deviceDistribution = $this->sessionRangeQuery($rangeStart, $rangeEnd)
            ->join('employees', 'employees.id', '=', 'work_sessions.employee_id')
            ->select('employees.name', DB::raw('COUNT(DISTINCT work_sessions.device_id) as devices_count'))
            ->groupBy('employees.id', 'employees.name')
            ->orderByDesc('devices_count')
            ->limit(8)
            ->get()
            ->map(fn ($employee) => [
                'name' => $employee->name,
                'devices' => $employee->devices_count,
            ]);

        $topWebsites = WebsiteLog::query()
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->select('domain', 'category', DB::raw('SUM(duration_seconds) as total_seconds'))
            ->groupBy('domain', 'category')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn ($site) => [
                'domain' => $site->domain,
                'category' => $site->category ?: 'Browsing',
                'duration' => $this->formatDuration((int) $site->total_seconds),
            ]);

        $domainAnalytics = WebsiteLog::query()
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->select('category', DB::raw('COUNT(DISTINCT domain) as domain_count'), DB::raw('SUM(duration_seconds) as total_seconds'))
            ->groupBy('category')
            ->orderByDesc('total_seconds')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category ?: 'Browsing',
                'domains' => (int) $row->domain_count,
                'duration' => $this->formatDuration((int) $row->total_seconds),
            ]);

        $employeeSessions = $this->sessionRangeQuery($rangeStart, $rangeEnd)
            ->with(['employee', 'device'])
            ->orderBy('started_at')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($sessions) use ($rangeStart, $rangeEnd, $singleDay) {
                $sortedSessions = $sessions->sortBy('started_at')->values();
                $firstSession = $sortedSessions->first();
                $employeeId = $firstSession?->employee_id;
                $hasOpenSession = $sortedSessions->contains(fn ($session) => $session->ended_at === null);
                $lastEndedAt = $sortedSessions
                    ->pluck('ended_at')
                    ->filter()
                    ->sort()
                    ->last();
                $firstNonIdleAt = $employeeId
                    ? ActivityLog::query()
                        ->where('employee_id', $employeeId)
                        ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                        ->where('activity_type', '!=', 'idle')
                        ->orderBy('started_at')
                        ->value('started_at')
                    : null;
                $firstAnyActivityAt = $employeeId
                    ? ActivityLog::query()
                        ->where('employee_id', $employeeId)
                        ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                        ->orderBy('started_at')
                        ->value('started_at')
                    : null;
                $latestActivityAt = $employeeId
                    ? ActivityLog::query()
                        ->where('employee_id', $employeeId)
                        ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                        ->selectRaw('MAX(COALESCE(ended_at, started_at)) as latest_at')
                        ->value('latest_at')
                    : null;
                $loginAt = $firstNonIdleAt ?? $firstAnyActivityAt ?? $firstSession?->started_at;
                $logoutAt = $hasOpenSession ? null : ($latestActivityAt ?? $lastEndedAt);
                $activeSeconds = $employeeId
                    ? (int) ActivityLog::query()
                        ->where('employee_id', $employeeId)
                        ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                        ->where('activity_type', '!=', 'idle')
                        ->sum('duration_seconds')
                    : 0;
                $idleSeconds = $employeeId
                    ? (int) ActivityLog::query()
                        ->where('employee_id', $employeeId)
                        ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                        ->where('activity_type', 'idle')
                        ->sum('duration_seconds')
                    : 0;

                if ($activeSeconds === 0 && $idleSeconds === 0) {
                    $activeSeconds = (int) $sortedSessions->sum(fn ($session) => (int) ($session->active_seconds ?? 0));
                    $idleSeconds = (int) $sortedSessions->sum(fn ($session) => (int) ($session->idle_seconds ?? 0));
                }
                $manualSeconds = $employeeId
                    ? (int) ManualTimeEntry::query()
                        ->where('employee_id', $employeeId)
                        ->whereDate('entry_date', '>=', $rangeStart->toDateString())
                        ->whereDate('entry_date', '<=', $rangeEnd->toDateString())
                        ->sum(DB::raw('minutes * 60'))
                    : 0;
                $deviceNames = $sortedSessions
                    ->map(fn ($session) => $session->device?->device_name)
                    ->filter()
                    ->unique()
                    ->implode(', ');

                $loginCarbon = $loginAt ? Carbon::parse((string) $loginAt) : null;
                $logoutCarbon = (! $hasOpenSession && $logoutAt) ? Carbon::parse((string) $logoutAt) : null;
                $inOutSeconds = ($loginCarbon && $logoutCarbon && $logoutCarbon->greaterThanOrEqualTo($loginCarbon))
                    ? $logoutCarbon->diffInSeconds($loginCarbon)
                    : 0;

                return [
                    'employee_id' => $firstSession?->employee_id,
                    'employee' => $firstSession?->employee?->name ?? 'Unknown Employee',
                    'employee_code' => $firstSession?->employee?->employee_code ?? '-',
                    'login' => $this->formatDateTime($loginAt ? Carbon::parse((string) $loginAt) : null, $singleDay),
                    'logout' => $hasOpenSession ? 'Open' : $this->formatDateTime($logoutAt ? Carbon::parse((string) $logoutAt) : null, $singleDay),
                    'in_out' => $inOutSeconds > 0 ? $this->formatDuration($inOutSeconds) : '-',
                    'active' => $this->formatDuration($activeSeconds),
                    'idle' => $this->formatDuration($idleSeconds),
                    'manual' => $this->formatDuration($manualSeconds),
                    'total' => $this->formatDuration($activeSeconds + $idleSeconds + $manualSeconds),
                    'work_time' => $this->formatDuration($activeSeconds + $idleSeconds),
                    'devices' => $deviceNames !== '' ? $deviceNames : 'Unassigned',
                    'sessions' => $sortedSessions->count(),
                ];
            })
            ->sortBy('employee')
            ->values();

        $reportComments = ReportComment::query()
            ->whereIn('employee_id', $employeeSessions->pluck('employee_id')->filter()->all())
            ->whereDate('report_from', $selectedFrom)
            ->whereDate('report_to', $selectedTo)
            ->get()
            ->keyBy('employee_id');

        $employeeSessions = $employeeSessions->map(function (array $session) use ($reportComments) {
            $session['comment'] = $session['employee_id']
                ? ($reportComments->get($session['employee_id'])?->comment ?? '')
                : '';

            return $session;
        });

        $ipAuditLogs = DeviceIpLog::query()
            ->with(['employee', 'device'])
            ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
            ->latest('recorded_at')
            ->limit(25)
            ->get()
            ->map(fn (DeviceIpLog $log) => [
                'employee' => $log->employee?->name ?? 'Unknown Employee',
                'device' => $log->device?->device_name ?? 'Unknown Device',
                'ip_address' => $log->ip_address,
                'time' => $this->formatDateTime($log->recorded_at, false),
                'source' => str_replace('_', ' ', ucfirst((string) $log->source)),
                'changed' => $log->is_changed,
            ]);

        return view('reports.index', [
            'pageTitle' => 'Reports',
            'selectedRangeLabel' => $selectedRangeLabel,
            'selectedPreset' => $selectedPreset,
            'selectedFrom' => $selectedFrom,
            'selectedTo' => $selectedTo,
            'reportCards' => $reportCards,
            'employeeSessions' => $employeeSessions,
            'idleLeaders' => $idleLeaders,
            'appBreakdown' => $appBreakdown,
            'systemEvents' => $systemEvents,
            'deviceDistribution' => $deviceDistribution,
            'manualAdjustments' => $manualAdjustments,
            'topWebsites' => $topWebsites,
            'domainAnalytics' => $domainAnalytics,
            'ipAuditLogs' => $ipAuditLogs,
            'onlineDevices' => Device::where('is_online', true)->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'unreadNotifications' => Notification::where('is_read', false)->count(),
        ]);
    }

    public function storeReportComment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'preset' => ['nullable', 'string'],
        ]);

        $comment = trim((string) ($validated['comment'] ?? ''));

        if ($comment === '') {
            ReportComment::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereDate('report_from', $validated['from'])
                ->whereDate('report_to', $validated['to'])
                ->delete();
        } else {
            ReportComment::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'report_from' => $validated['from'],
                    'report_to' => $validated['to'],
                ],
                [
                    'comment' => $comment,
                    'created_by' => auth()->id(),
                ]
            );
        }

        $redirectParams = array_filter([
            'preset' => $validated['preset'] ?? null,
            'from' => $validated['from'],
            'to' => $validated['to'],
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('reports.index', $redirectParams)
            ->with('status', 'Report comment saved.');
    }

    public function reportEmployee(Request $request, Employee $employee): View
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd, $selectedRangeLabel, $selectedPreset, $singleDay, $selectedFrom, $selectedTo] = $this->resolveReportRange($request);

       /*  $sessions = $this->sessionRangeQuery($rangeStart, $rangeEnd)
            ->with('device')
            ->where('employee_id', $employee->id)
            ->orderBy('started_at')
            ->get(); */
			
		$sessions = $this->sessionRangeQuery($rangeStart, $rangeEnd)
    ->with('device')
    ->where('employee_id', $employee->id)
    ->orderBy('started_at')
    ->get();

// 🔥 NEW: group by date
  $groupedSessions = $sessions->groupBy(function ($session) {
    return \Carbon\Carbon::parse($session->started_at)->format('Y-m-d');
    });	
	
	
	$selectedDate = $request->date;

$daySessions = collect();

    if ($selectedDate) {
    $daySessions = $sessions->filter(function ($session) use ($selectedDate) {
        return \Carbon\Carbon::parse($session->started_at)->format('Y-m-d') === $selectedDate;
    });
    }

        $sortedSessions = $sessions->sortBy('started_at')->values();
        $firstSession = $sortedSessions->first();
        $hasOpenSession = $sortedSessions->contains(fn ($session) => $session->ended_at === null);
        $lastEndedAt = $sortedSessions->pluck('ended_at')->filter()->sort()->last();

        $activeSeconds = (int) ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->where('activity_type', '!=', 'idle')
            ->sum('duration_seconds');
        $idleSeconds = (int) ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->where('activity_type', 'idle')
            ->sum('duration_seconds');

        if ($activeSeconds === 0 && $idleSeconds === 0) {
            $activeSeconds = (int) $sortedSessions->sum(fn ($session) => (int) ($session->active_seconds ?? 0));
            $idleSeconds = (int) $sortedSessions->sum(fn ($session) => (int) ($session->idle_seconds ?? 0));
        }

        $firstNonIdleAt = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->where('activity_type', '!=', 'idle')
            ->orderBy('started_at')
            ->value('started_at');
        $firstAnyActivityAt = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->orderBy('started_at')
            ->value('started_at');
        $latestActivityAt = ActivityLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->selectRaw('MAX(COALESCE(ended_at, started_at)) as latest_at')
            ->value('latest_at');
        $loginAt = $firstNonIdleAt ?? $firstAnyActivityAt ?? $firstSession?->started_at;
        $logoutAt = $hasOpenSession ? null : ($latestActivityAt ?? $lastEndedAt);

        $appUsage = ActivityLog::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->select('app_name', 'category', DB::raw('SUM(duration_seconds) as total_seconds'), DB::raw('MAX(ended_at) as last_ended_at'))
            ->groupBy('app_name', 'category')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn ($app) => [
                'app' => $app->app_name,
                'category' => $app->category ?: 'General',
                'duration' => $this->formatDuration((int) $app->total_seconds),
                'last_used' => $app->last_ended_at ? Carbon::parse($app->last_ended_at)->timezone('Asia/Kolkata')->format($singleDay ? 'H:i' : 'd M Y H:i') : '-',
            ]);

        $websiteUsage = WebsiteLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->select('domain', 'browser_name', DB::raw('SUM(duration_seconds) as total_seconds'), DB::raw('MAX(ended_at) as last_ended_at'))
            ->groupBy('domain', 'browser_name')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn ($site) => [
                'domain' => $site->domain,
                'browser' => $site->browser_name,
                'duration' => $this->formatDuration((int) $site->total_seconds),
                'last_used' => $site->last_ended_at ? Carbon::parse($site->last_ended_at)->timezone('Asia/Kolkata')->format($singleDay ? 'H:i' : 'd M Y H:i') : '-',
            ]);
			
			$websiteByDate = \App\Models\WebsiteLog::query()
    ->where('employee_id', $employee->id)
    ->whereBetween('started_at', [$rangeStart, $rangeEnd])
    ->selectRaw('DATE(started_at) as date, domain, SUM(duration_seconds) as total_seconds')
    ->groupBy('date', 'domain')
    ->get()
    ->groupBy('date')
    ->map(function ($items) {
        return $items->sortByDesc('total_seconds')->take(10);
    });

        $activityTimeline = ActivityLog::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'app' => $log->app_name,
                'window' => $log->window_title ?: 'Untitled Window',
                'device' => $log->device?->device_name ?? 'Unknown Device',
                'type' => $log->activity_type === 'idle' ? 'Idle' : 'Active',
                'duration' => $this->formatDuration((int) $log->duration_seconds),
                'time' => $this->formatDateTime($log->ended_at ?? $log->started_at, false),
            ]);

        $systemEvents = SystemEvent::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->whereBetween('occurred_at', [$rangeStart, $rangeEnd])
            ->latest('occurred_at')
            ->limit(15)
            ->get()
            ->map(fn (SystemEvent $event) => [
                'event' => str_replace('_', ' ', ucfirst($event->event_type)),
                'device' => $event->device?->device_name ?? 'Unknown Device',
                'time' => $this->formatDateTime($event->occurred_at, false),
            ]);

        $ipAuditLogs = DeviceIpLog::query()
            ->with('device')
            ->where('employee_id', $employee->id)
            ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
            ->latest('recorded_at')
            ->limit(15)
            ->get()
            ->map(fn (DeviceIpLog $log) => [
                'device' => $log->device?->device_name ?? 'Unknown Device',
                'ip_address' => $log->ip_address,
                'source' => str_replace('_', ' ', ucfirst((string) $log->source)),
                'time' => $this->formatDateTime($log->recorded_at, false),
                'changed' => $log->is_changed,
            ]);

        $manualSeconds = (int) ManualTimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereDate('entry_date', '>=', $rangeStart->toDateString())
            ->whereDate('entry_date', '<=', $rangeEnd->toDateString())
            ->sum(DB::raw('minutes * 60'));

        $reportCommentsByDate = ReportComment::query()
            ->where('employee_id', $employee->id)
            ->whereColumn('report_from', 'report_to')
            ->whereDate('report_from', '>=', $rangeStart->toDateString())
            ->whereDate('report_from', '<=', $rangeEnd->toDateString())
            ->get()
            ->keyBy(fn (ReportComment $comment) => $comment->report_from?->toDateString());

        return view('reports.show', [
            'pageTitle' => $employee->name.' Report',
            'employee' => $employee,
            'selectedRangeLabel' => $selectedRangeLabel,
            'selectedPreset' => $selectedPreset,
            'selectedFrom' => $selectedFrom,
            'selectedTo' => $selectedTo,
			'sessions' => $groupedSessions, 
            'daySessions' => $daySessions,
            'selectedDate' => $selectedDate,
			'websiteByDate' => $websiteByDate,
            'summary' => [
                'login' => $this->formatDateTime($loginAt ? Carbon::parse((string) $loginAt) : null, $singleDay),
                'logout' => $hasOpenSession ? 'Open' : $this->formatDateTime($logoutAt ? Carbon::parse((string) $logoutAt) : null, $singleDay),
                'active' => $this->formatDuration($activeSeconds),
                'idle' => $this->formatDuration($idleSeconds),
                'manual' => $this->formatDuration($manualSeconds),
                'total' => $this->formatDuration($activeSeconds + $idleSeconds + $manualSeconds),
                'devices' => $sortedSessions->map(fn ($session) => $session->device?->device_name)->filter()->unique()->implode(', ') ?: 'Unassigned',
            ],
            'appUsage' => $appUsage,
            'websiteUsage' => $websiteUsage,
            'reportCommentsByDate' => $reportCommentsByDate,
            'activityTimeline' => $activityTimeline,
            'systemEvents' => $systemEvents,
            'ipAuditLogs' => $ipAuditLogs,
            'onlineDevices' => Device::where('is_online', true)->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'unreadNotifications' => Notification::where('is_read', false)->count(),
        ]);
    }

    public function settings(): View
    {
        return view('settings.index', [
            'pageTitle' => 'Settings',
            'settingsSections' => [
                ['title' => 'Agent Configuration', 'detail' => 'Sync interval, heartbeat frequency, idle threshold, startup mode, and watchdog process.'],
                ['title' => 'Productivity Rules', 'detail' => 'Mark applications and domains as productive, neutral, or unproductive.'],
                ['title' => 'Notifications', 'detail' => 'Offline or missing-heartbeat alerts are generated when a device stays silent for more than 10 minutes.'],
                ['title' => 'Reliability', 'detail' => 'Use install_startup.py and watchdog.py on Windows to keep the agent running after login or unexpected exits.'],
            ],
        ]);
    }

    private function todayRange(): array
    {
        $start = Carbon::now('Asia/Kolkata')->startOfDay();

        return [$start, $start->copy()->endOfDay()];
    }

    private function resolveReportRange(Request $request): array
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:today,yesterday,this_week,this_month,custom'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $preset = $validated['preset'] ?? null;

        if (($validated['from'] ?? null) && ($validated['to'] ?? null)) {
            $start = Carbon::parse((string) $validated['from'], 'Asia/Kolkata')->startOfDay();
            $end = Carbon::parse((string) $validated['to'], 'Asia/Kolkata')->endOfDay();

            return [$start, $end, sprintf('%s to %s', $start->format('d M Y'), $end->format('d M Y')), 'custom', $start->isSameDay($end), $start->toDateString(), $end->toDateString()];
        }

        $anchor = isset($validated['date'])
            ? Carbon::parse((string) $validated['date'], 'Asia/Kolkata')
            : Carbon::now('Asia/Kolkata');

        [$start, $end, $resolvedPreset] = match ($preset) {
            'yesterday' => [$anchor->copy()->subDay()->startOfDay(), $anchor->copy()->subDay()->endOfDay(), 'yesterday'],
            'this_week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), 'this_week'],
            'this_month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), 'this_month'],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay(), 'today'],
        };

        return [$start, $end, $start->isSameDay($end) ? $start->format('d M Y') : sprintf('%s to %s', $start->format('d M Y'), $end->format('d M Y')), $resolvedPreset, $start->isSameDay($end), $start->toDateString(), $end->toDateString()];
    }

    private function sessionRangeQuery(Carbon $start, Carbon $end)
    {
        return WorkSession::query()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('started_at', [$start, $end])
                    ->orWhereBetween('ended_at', [$start, $end])
                    ->orWhere(function ($overlap) use ($start, $end) {
                        $overlap->where('started_at', '<', $start)
                            ->where(function ($tail) use ($end) {
                                $tail->whereNull('ended_at')
                                    ->orWhere('ended_at', '>', $end);
                            });
                    });
            });
    }

    private function closeStaleSessions(?Carbon $referenceTime = null): void
    {
        $cutoff = ($referenceTime ?? Carbon::now('Asia/Kolkata'))->copy()->subMinutes(10);

        WorkSession::query()
            ->with('device')
            ->whereNull('ended_at')
            ->where(function ($query) use ($cutoff) {
                $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff))
                    ->orWhere('updated_at', '<', $cutoff);
            })
            ->get()
            ->each(function (WorkSession $session) {
                $logoutAt = $session->device?->last_seen_at ?? $session->updated_at ?? $session->started_at;

                $session->update([
                    'ended_at' => $logoutAt,
                    'logout_at' => $logoutAt,
                    'status' => 'ended',
                ]);

                if ($session->device && $session->device->is_online) {
                    $session->device->update([
                        'is_online' => false,
                    ]);
                }
            });
    }

    private function formatDateTime(?Carbon $value, bool $singleDay = true): string
    {
        if (! $value) {
            return '-';
        }

        return $value->timezone('Asia/Kolkata')->format($singleDay ? 'H:i' : 'd M Y H:i');
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return $minutes.'m';
        }

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    private function deviceStatus(bool $isOnline, ?Carbon $lastSeen): string
    {
        if (! $lastSeen || ! $isOnline) {
            return 'Offline';
        }

        return $lastSeen->greaterThanOrEqualTo(now()->subMinutes(5)) ? 'Online' : 'Idle';
    }

    private function liveStatus(bool $isOnline, ?Carbon $lastSeen, ?string $activityType): string
    {
        if (! $lastSeen || ! $isOnline || $lastSeen->lt(now()->subMinutes(5))) {
            return 'Offline';
        }

        return $activityType === 'idle' ? 'Idle' : 'Active';
    }
}