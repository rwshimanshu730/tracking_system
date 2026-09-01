<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeviceIpLog;
use App\Models\ManualTimeEntry;
use App\Models\WebsiteLog;
use App\Models\WorkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function dailyCsv(Request $request): StreamedResponse
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd, $rangeLabel, $singleDay] = $this->resolveRange($request);
        $rows = $this->sessionRangeQuery($rangeStart, $rangeEnd)
            ->with('employee')
            ->get()
            ->map(fn ($session) => [
                $session->employee?->name ?? 'Unknown',
                $this->formatDateTime($session->started_at, $singleDay),
                $session->ended_at ? $this->formatDateTime($session->ended_at, $singleDay) : 'Open',
                $session->active_seconds,
                $session->idle_seconds,
                $session->productivity_score,
            ]);

        return $this->csvResponse(
            'daily-report-'.$rangeStart->format('Y-m-d').'-to-'.$rangeEnd->format('Y-m-d').'.csv',
            ['Employee', 'Started At', 'Ended At', 'Active Seconds', 'Idle Seconds', 'Productivity Score'],
            $rows->all()
        );
    }

    public function appUsageCsv(Request $request): StreamedResponse
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd] = $this->resolveRange($request);
        $rows = ActivityLog::query()
            ->whereBetween('started_at', [$rangeStart, $rangeEnd])
            ->selectRaw('app_name, category, SUM(duration_seconds) as total_seconds, SUM(keyboard_events) as keyboard_events, SUM(mouse_events) as mouse_events')
            ->groupBy('app_name', 'category')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn ($log) => [
                $log->app_name,
                $log->category,
                $log->total_seconds,
                $log->keyboard_events,
                $log->mouse_events,
            ]);

        return $this->csvResponse(
            'app-usage-report-'.$rangeStart->format('Y-m-d').'-to-'.$rangeEnd->format('Y-m-d').'.csv',
            ['Application', 'Category', 'Total Seconds', 'Keyboard Events', 'Mouse Events'],
            $rows->all()
        );
    }

    public function manualEntriesCsv(Request $request): StreamedResponse
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd] = $this->resolveRange($request);
        $rows = ManualTimeEntry::with(['employee', 'creator'])
            ->whereDate('entry_date', '>=', $rangeStart->toDateString())
            ->whereDate('entry_date', '<=', $rangeEnd->toDateString())
            ->latest('entry_date')
            ->get()
            ->map(fn ($entry) => [
                $entry->entry_date?->format('Y-m-d'),
                $entry->employee?->name,
                $entry->entry_type,
                $entry->minutes,
                $entry->reason,
                $entry->creator?->name,
            ]);

        return $this->csvResponse(
            'manual-time-entries-'.$rangeStart->format('Y-m-d').'-to-'.$rangeEnd->format('Y-m-d').'.csv',
            ['Date', 'Employee', 'Type', 'Minutes', 'Reason', 'Created By'],
            $rows->all()
        );
    }

    public function dailyJson(Request $request): JsonResponse
    {
        $this->closeStaleSessions();
        [$rangeStart, $rangeEnd, $rangeLabel, $singleDay] = $this->resolveRange($request);

        $sessions = $this->sessionRangeQuery($rangeStart, $rangeEnd)
            ->with(['employee', 'device'])
            ->orderBy('started_at')
            ->get()
            ->groupBy('employee_id');

        $employees = $sessions->map(function ($employeeSessions) use ($rangeStart, $rangeEnd, $singleDay) {
            $sortedSessions = $employeeSessions->sortBy('started_at')->values();
            $firstSession = $sortedSessions->first();
            $employeeId = $firstSession?->employee_id;

            $appTotals = $employeeId
                ? ActivityLog::query()
                    ->where('employee_id', $employeeId)
                    ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                    ->selectRaw('app_name, category, SUM(duration_seconds) as total_seconds')
                    ->groupBy('app_name', 'category')
                    ->orderByDesc('total_seconds')
                    ->get()
                    ->map(fn ($app) => [
                        'app' => $app->app_name,
                        'category' => $app->category,
                        'duration' => $this->formatDuration((int) $app->total_seconds),
                    ])
                    ->values()
                : collect();

            $websiteTotals = $employeeId
                ? WebsiteLog::query()
                    ->where('employee_id', $employeeId)
                    ->whereBetween('started_at', [$rangeStart, $rangeEnd])
                    ->selectRaw('domain, category, SUM(duration_seconds) as total_seconds')
                    ->groupBy('domain', 'category')
                    ->orderByDesc('total_seconds')
                    ->get()
                    ->map(fn ($site) => [
                        'domain' => $site->domain,
                        'category' => $site->category,
                        'duration' => $this->formatDuration((int) $site->total_seconds),
                    ])
                    ->values()
                : collect();

            $ipLogs = $employeeId
                ? DeviceIpLog::query()
                    ->where('employee_id', $employeeId)
                    ->whereBetween('recorded_at', [$rangeStart, $rangeEnd])
                    ->with('device')
                    ->latest('recorded_at')
                    ->get()
                    ->map(fn ($log) => [
                        'device' => $log->device?->device_name ?? 'Unknown Device',
                        'ip_address' => $log->ip_address,
                        'recorded_at' => $this->formatDateTime($log->recorded_at, false),
                        'source' => $log->source,
                        'changed' => $log->is_changed,
                    ])
                    ->values()
                : collect();

            return [
                'employee' => $firstSession?->employee?->name ?? 'Unknown Employee',
                'employee_code' => $firstSession?->employee?->employee_code ?? '-',
                'first_login' => $this->formatDateTime($firstSession?->started_at, $singleDay),
                'last_logout' => $sortedSessions->contains(fn ($session) => $session->ended_at === null)
                    ? 'Open'
                    : $this->formatDateTime($sortedSessions->pluck('ended_at')->filter()->sort()->last(), $singleDay),
                'devices' => $sortedSessions->map(fn ($session) => $session->device?->device_name)->filter()->unique()->values(),
                'sessions' => $sortedSessions->map(fn ($session) => [
                    'started_at' => $this->formatDateTime($session->started_at, false),
                    'ended_at' => $session->ended_at ? $this->formatDateTime($session->ended_at, false) : 'Open',
                    'active_seconds' => (int) $session->active_seconds,
                    'idle_seconds' => (int) $session->idle_seconds,
                    'status' => $session->status,
                ])->values(),
                'app_totals' => $appTotals,
                'website_totals' => $websiteTotals,
                'ip_logs' => $ipLogs,
            ];
        })->values();

        return response()->json([
            'range' => [
                'label' => $rangeLabel,
                'from' => $rangeStart->toIso8601String(),
                'to' => $rangeEnd->toIso8601String(),
                'timezone' => config('app.timezone'),
            ],
            'employees' => $employees,
        ]);
    }

    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        $callback = function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:today,yesterday,this_week,this_month,custom'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if (($validated['from'] ?? null) && ($validated['to'] ?? null)) {
            $start = Carbon::parse((string) $validated['from'], 'Asia/Kolkata')->startOfDay();
            $end = Carbon::parse((string) $validated['to'], 'Asia/Kolkata')->endOfDay();

            return [$start, $end, sprintf('%s to %s', $start->format('d M Y'), $end->format('d M Y')), $start->isSameDay($end)];
        }

        $anchor = isset($validated['date'])
            ? Carbon::parse((string) $validated['date'], 'Asia/Kolkata')
            : Carbon::now('Asia/Kolkata');

        [$start, $end] = match ($validated['preset'] ?? 'today') {
            'yesterday' => [$anchor->copy()->subDay()->startOfDay(), $anchor->copy()->subDay()->endOfDay()],
            'this_week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'this_month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };

        return [$start, $end, $start->isSameDay($end) ? $start->format('d M Y') : sprintf('%s to %s', $start->format('d M Y'), $end->format('d M Y')), $start->isSameDay($end)];
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

    private function closeStaleSessions(): void
    {
        $cutoff = Carbon::now('Asia/Kolkata')->subMinutes(10);

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

        return $value->timezone('Asia/Kolkata')->format($singleDay ? 'H:i' : 'Y-m-d H:i:s');
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
}
