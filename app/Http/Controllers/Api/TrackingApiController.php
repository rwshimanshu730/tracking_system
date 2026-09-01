<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceIpLog;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\ProductivityRule;
use App\Models\SystemEvent;
use App\Models\WebsiteLog;
use App\Models\WorkSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TrackingApiController extends Controller
{
    public function activateAgent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
        ]);

        $employee = Employee::query()
            ->where('employee_code', $payload['employee_code'])
            ->first();

        if (! $employee) {
            return response()->json([
                'message' => 'Employee code was not found. Please contact HR or admin.',
            ], 404);
        }

        $device = Device::query()
            ->where('machine_name', $payload['machine_name'])
            ->first();

        if ($device && $device->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'This desktop is already linked to another employee.',
            ], 409);
        }

        $plainToken = 'trk_'.Str::random(40);

        if ($device) {
            $device->update([
                'employee_id' => $employee->id,
                'device_name' => $payload['device_name'],
                'os_name' => $payload['os_name'] ?? $device->os_name,
                'agent_version' => $payload['agent_version'] ?? $device->agent_version,
                'api_token' => hash('sha256', $plainToken),
                'last_seen_at' => now(),
                'is_online' => true,
            ]);
        } else {
            $device = Device::create([
                'employee_id' => $employee->id,
                'device_name' => $payload['device_name'],
                'machine_name' => $payload['machine_name'],
                'os_name' => $payload['os_name'] ?? null,
                'agent_version' => $payload['agent_version'] ?? null,
                'api_token' => hash('sha256', $plainToken),
                'last_seen_at' => now(),
                'is_online' => true,
            ]);
        }

        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            now(),
            'activation'
        );

        return response()->json([
            'message' => 'Agent activated successfully.',
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'device_id' => $device->id,
            'device_name' => $device->device_name,
            'machine_name' => $device->machine_name,
            'api_token' => $plainToken,
        ], 201);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
        ]);

        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->upsertDevice($employee, $payload);
        $session = $this->ensureOpenSession($employee, $device, now());
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            now(),
            'device_registration'
        );

        return response()->json([
            'message' => 'Device registered successfully.',
            'device_id' => $device->id,
            'employee_id' => $employee->id,
            'work_session_id' => $session->id,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'last_seen_at' => ['nullable', 'date'],
            'is_online' => ['nullable', 'boolean'],
        ]);

        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $seenAt = isset($payload['last_seen_at']) ? Carbon::parse($payload['last_seen_at']) : now();
        $session = $this->ensureOpenSession($employee, $device, $seenAt);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            $seenAt,
            'heartbeat'
        );

        $device->update([
            'employee_id' => $employee->id,
            'device_name' => $payload['device_name'],
            'last_seen_at' => $seenAt,
            'is_online' => $payload['is_online'] ?? true,
        ]);

        return response()->json([
            'message' => 'Heartbeat recorded successfully.',
            'device_id' => $device->id,
            'work_session_id' => $session->id,
        ]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'event_type' => ['nullable', 'string', 'max:50'],
        ]);

        $occurredAt = isset($payload['started_at']) ? Carbon::parse($payload['started_at']) : now();
        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $session = $this->ensureOpenSession($employee, $device, $occurredAt);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            $occurredAt,
            'session_start'
        );

        $device->update([
            'last_seen_at' => $occurredAt,
            'is_online' => true,
        ]);

        $this->recordSystemEvent($employee, $device, $session, $payload['event_type'] ?? 'session_start', $occurredAt, $payload);

        return response()->json([
            'message' => 'Session started successfully.',
            'work_session_id' => $session->id,
        ]);
    }

    public function endSession(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'ended_at' => ['nullable', 'date'],
            'event_type' => ['nullable', 'string', 'max:50'],
        ]);

        $occurredAt = isset($payload['ended_at']) ? Carbon::parse($payload['ended_at']) : now();
        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $session = $this->resolveCurrentSession($employee, $device, $occurredAt);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            $occurredAt,
            'session_end'
        );

        $session->update([
            'ended_at' => $occurredAt,
            'logout_at' => $occurredAt,
            'status' => 'ended',
        ]);

        $device->update([
            'last_seen_at' => $occurredAt,
            'is_online' => false,
        ]);

        $this->recordSystemEvent($employee, $device, $session, $payload['event_type'] ?? 'session_end', $occurredAt, $payload);

        return response()->json([
            'message' => 'Session ended successfully.',
            'work_session_id' => $session->id,
        ]);
    }

    public function storeSystemEvent(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'device_name' => ['required', 'string', 'max:255'],
            'machine_name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:50'],
            'occurred_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ]);

        $occurredAt = isset($payload['occurred_at']) ? Carbon::parse($payload['occurred_at']) : now();
        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $session = in_array($payload['event_type'], ['shutdown', 'session_end'], true)
            ? $this->resolveCurrentSession($employee, $device, $occurredAt)
            : $this->ensureOpenSession($employee, $device, $occurredAt);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            $occurredAt,
            $payload['event_type']
        );

        $this->recordSystemEvent($employee, $device, $session, $payload['event_type'], $occurredAt, $payload['payload'] ?? []);

        if (in_array($payload['event_type'], ['shutdown', 'session_end'], true)) {
            $session->update([
                'ended_at' => $occurredAt,
                'logout_at' => $occurredAt,
                'status' => 'ended',
            ]);

            $device->update([
                'last_seen_at' => $occurredAt,
                'is_online' => false,
            ]);
        } else {
            $device->update([
                'last_seen_at' => $occurredAt,
                'is_online' => true,
            ]);
        }

        return response()->json([
            'message' => 'System event stored successfully.',
        ], 201);
    }

    public function storeActivityLog(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'machine_name' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'app_name' => ['required', 'string', 'max:255'],
            'window_title' => ['nullable', 'string', 'max:65000'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'activity_type' => ['nullable', 'string', 'max:50'],
            'is_productive' => ['nullable', 'boolean'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'keyboard_events' => ['nullable', 'integer', 'min:0'],
            'mouse_events' => ['nullable', 'integer', 'min:0'],
            'recorded_on' => ['required', 'date'],
        ]);

        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            isset($payload['ended_at']) ? Carbon::parse($payload['ended_at']) : Carbon::parse($payload['started_at']),
            'activity_log'
        );

        $activityLog = $this->storeActivityPayload($payload);

        return response()->json([
            'message' => 'Activity log stored successfully.',
            'activity_log_id' => $activityLog->id,
            'work_session_id' => $activityLog->work_session_id,
        ], 201);
    }

    public function storeWebsiteLog(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'machine_name' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:255'],
            'browser_name' => ['required', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:65000'],
            'url' => ['required', 'url', 'max:2048'],
            'domain' => ['nullable', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_productive' => ['nullable', 'boolean'],
            'recorded_on' => ['required', 'date'],
        ]);

        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $this->recordRequestIp(
            $this->resolveRequestIp($request),
            $employee,
            $device,
            isset($payload['ended_at']) ? Carbon::parse($payload['ended_at']) : Carbon::parse($payload['started_at']),
            'website_log'
        );

        $websiteLog = $this->storeWebsitePayload($payload);

        return response()->json([
            'message' => 'Website log stored successfully.',
            'website_log_id' => $websiteLog->id,
            'work_session_id' => $websiteLog->work_session_id,
        ], 201);
    }

    public function batchSync(Request $request): JsonResponse
    {
        $this->authorizeDeviceToken($request);

        $payload = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.type' => ['required', 'string', 'max:50'],
            'events.*.payload' => ['required', 'array'],
        ]);

        $processed = [];
        $requestIp = $this->resolveRequestIp($request);

        foreach ($payload['events'] as $index => $event) {
            $type = $event['type'];
            $eventPayload = $event['payload'];

            $this->recordRequestIpFromPayload($eventPayload, $type, $requestIp);

            match ($type) {
                'device_registration' => $this->registerDevice(new Request($eventPayload)),
                'heartbeat' => $this->heartbeat(new Request($eventPayload)),
                'activity_log' => $this->storeActivityPayload($eventPayload),
                'website_log' => $this->storeWebsitePayload($eventPayload),
                'session_start' => $this->startSession(new Request($eventPayload)),
                'session_end' => $this->endSession(new Request($eventPayload)),
                'system_event' => $this->storeSystemEvent(new Request($eventPayload)),
                default => null,
            };

            $processed[] = ['index' => $index, 'type' => $type];
        }

        return response()->json([
            'message' => 'Batch sync completed successfully.',
            'processed' => $processed,
        ]);
    }

    private function storeActivityPayload(array $payload): ActivityLog
    {
        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $endedAt = isset($payload['ended_at']) ? Carbon::parse($payload['ended_at']) : Carbon::parse($payload['started_at']);
        $startedAt = Carbon::parse($payload['started_at']);
        $workSession = $this->ensureOpenSession($employee, $device, $startedAt);

        return DB::transaction(function () use ($payload, $employee, $device, $workSession, $startedAt, $endedAt) {
            $duration = (int) ($payload['duration_seconds'] ?? max(1, $startedAt->diffInSeconds($endedAt)));
            $activityType = $payload['activity_type'] ?? 'active_window';
            $rule = $this->resolveProductivityRule($payload['app_name'], $payload['window_title'] ?? null);
            $isProductive = match ($rule?->productivity_type) {
                'productive' => true,
                'unproductive' => false,
                default => $payload['is_productive'] ?? ($activityType !== 'idle'),
            };

            $activityLog = ActivityLog::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'work_session_id' => $workSession->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'app_name' => $payload['app_name'],
                'window_title' => $payload['window_title'] ?? null,
                'category' => $rule?->category ?? $this->guessCategory($payload['app_name']),
                'activity_type' => $activityType,
                'is_productive' => $isProductive,
                'duration_seconds' => $duration,
                'keyboard_events' => (int) ($payload['keyboard_events'] ?? 0),
                'mouse_events' => (int) ($payload['mouse_events'] ?? 0),
                'recorded_on' => Carbon::parse($payload['recorded_on'])->toDateString(),
            ]);

            $activeSeconds = (int) ($workSession->active_seconds ?? 0);
            $idleSeconds = (int) ($workSession->idle_seconds ?? 0);

            if ($activityType === 'idle') {
                $idleSeconds += $duration;
            } else {
                $activeSeconds += $duration;
            }

            $totalSeconds = max(1, $activeSeconds + $idleSeconds);

            $workSession->update([
                'active_seconds' => $activeSeconds,
                'idle_seconds' => $idleSeconds,
                'productivity_score' => round(($activeSeconds / $totalSeconds) * 100, 2),
                'status' => $activityType === 'idle' ? 'idle' : 'running',
            ]);

            $device->update([
                'last_seen_at' => $endedAt,
                'is_online' => true,
            ]);

            return $activityLog;
        });
    }

    private function storeWebsitePayload(array $payload): WebsiteLog
    {
        $employee = $this->resolveEmployee($payload['employee_code']);
        $device = $this->resolveDevice($employee, $payload['machine_name'], $payload['device_name']);
        $startedAt = Carbon::parse($payload['started_at']);
        $endedAt = isset($payload['ended_at']) ? Carbon::parse($payload['ended_at']) : $startedAt;
        $workSession = $this->ensureOpenSession($employee, $device, $startedAt);

        return DB::transaction(function () use ($payload, $employee, $device, $workSession, $startedAt, $endedAt) {
            $domain = $payload['domain'] ?? parse_url($payload['url'], PHP_URL_HOST) ?? 'unknown';
            $duration = (int) ($payload['duration_seconds'] ?? max(1, $startedAt->diffInSeconds($endedAt)));
            $rule = $this->resolveProductivityRule($payload['browser_name'], $payload['page_title'] ?? null, $domain);
            $isProductive = match ($rule?->productivity_type) {
                'productive' => true,
                'unproductive' => false,
                default => $payload['is_productive'] ?? true,
            };

            $websiteLog = WebsiteLog::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'work_session_id' => $workSession->id,
                'browser_name' => $payload['browser_name'],
                'page_title' => $payload['page_title'] ?? null,
                'url' => $payload['url'],
                'domain' => strtolower((string) $domain),
                'category' => $rule?->category ?? $this->guessWebsiteCategory((string) $domain),
                'is_productive' => $isProductive,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'recorded_on' => Carbon::parse($payload['recorded_on'])->toDateString(),
            ]);

            // Website events are stored for analytics only. Session counters are maintained
            // by activity logs to avoid counting overlapping app + website durations twice.
            $workSession->update([
                'status' => 'running',
            ]);

            $device->update([
                'last_seen_at' => $endedAt,
                'is_online' => true,
            ]);

            return $websiteLog;
        });
    }

    private function resolveEmployee(string $employeeCode): Employee
    {
        return Employee::firstOrCreate(
            ['employee_code' => $employeeCode],
            [
                'name' => $employeeCode,
                'employment_status' => 'active',
            ]
        );
    }

    private function upsertDevice(Employee $employee, array $payload): Device
    {
        return Device::updateOrCreate(
            ['machine_name' => $payload['machine_name']],
            [
                'employee_id' => $employee->id,
                'device_name' => $payload['device_name'],
                'os_name' => $payload['os_name'] ?? null,
                'agent_version' => $payload['agent_version'] ?? null,
                'last_seen_at' => now(),
                'is_online' => true,
            ]
        );
    }

    private function resolveDevice(Employee $employee, string $machineName, string $deviceName): Device
    {
        return Device::firstOrCreate(
            ['machine_name' => $machineName],
            [
                'employee_id' => $employee->id,
                'device_name' => $deviceName,
                'is_online' => true,
            ]
        );
    }

    private function ensureOpenSession(Employee $employee, Device $device, Carbon $referenceTime): WorkSession
    {
        $openSession = WorkSession::query()
            ->where('employee_id', $employee->id)
            ->where('device_id', $device->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if ($openSession) {
            $lastActivityAt = $openSession->updated_at ?? $openSession->started_at;
            $shouldCloseSession = $lastActivityAt instanceof Carbon
                && (
                    $lastActivityAt->copy()->diffInMinutes($referenceTime) > 10
                    || $openSession->started_at?->toDateString() !== $referenceTime->toDateString()
                );

            if ($shouldCloseSession) {
                $openSession->update([
                    'ended_at' => $lastActivityAt,
                    'logout_at' => $lastActivityAt,
                    'status' => 'ended',
                ]);
            } else {
                return $openSession;
            }
        }

        return WorkSession::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'started_at' => $referenceTime,
            'login_at' => $referenceTime,
            'status' => 'running',
        ]);
    }

    private function resolveCurrentSession(Employee $employee, Device $device, Carbon $referenceTime): WorkSession
    {
        return WorkSession::query()
            ->where('employee_id', $employee->id)
            ->where('device_id', $device->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first() ?? $this->ensureOpenSession($employee, $device, $referenceTime);
    }

    private function recordSystemEvent(Employee $employee, Device $device, WorkSession $session, string $eventType, Carbon $occurredAt, array $payload = []): SystemEvent
    {
        $event = SystemEvent::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'work_session_id' => $session->id,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ]);

        if (in_array($eventType, ['unexpected_shutdown', 'watchdog_restart', 'system_shutdown'], true)) {
            Notification::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'type' => 'tamper_or_recovery',
                'title' => 'Agent reliability event',
                'message' => sprintf('%s reported %s.', $device->device_name, str_replace('_', ' ', $eventType)),
                'raised_at' => $occurredAt,
            ]);
        }

        return $event;
    }

    private function guessCategory(string $appName): string
    {
        return match (strtolower($appName)) {
            'code', 'devenv', 'phpstorm', 'pycharm', 'webstorm' => 'Development',
            'chrome', 'msedge', 'firefox', 'brave' => 'Browsing',
            'figma', 'photoshop', 'illustrator' => 'Design',
            'slack', 'teams', 'discord' => 'Communication',
            'cmd', 'powershell', 'windows terminal' => 'Terminal',
            default => 'General',
        };
    }

    private function guessWebsiteCategory(string $domain): string
    {
        return match (true) {
            str_contains($domain, 'github.com'),
            str_contains($domain, 'stackoverflow.com'),
            str_contains($domain, 'gitlab.com') => 'Development',
            str_contains($domain, 'docs.'),
            str_contains($domain, 'developer.') => 'Documentation',
            str_contains($domain, 'meet.google.com'),
            str_contains($domain, 'zoom.us'),
            str_contains($domain, 'teams.microsoft.com') => 'Meetings',
            str_contains($domain, 'youtube.com'),
            str_contains($domain, 'netflix.com'),
            str_contains($domain, 'instagram.com'),
            str_contains($domain, 'facebook.com') => 'Entertainment',
            default => 'Browsing',
        };
    }

    private function resolveProductivityRule(string $appName, ?string $windowTitle, ?string $domain = null): ?ProductivityRule
    {
        return ProductivityRule::query()
            ->where('is_active', true)
            ->get()
            ->first(function (ProductivityRule $rule) use ($appName, $windowTitle, $domain) {
                if ($rule->match_type === 'app_name') {
                    return str_contains(strtolower($appName), strtolower($rule->match_value));
                }

                if ($rule->match_type === 'window_title') {
                    return $windowTitle !== null && str_contains(strtolower($windowTitle), strtolower($rule->match_value));
                }

                return $domain !== null && str_contains(strtolower($domain), strtolower($rule->match_value));
            });
    }

    private function authorizeDeviceToken(Request $request): void
    {
        $token = $request->bearerToken();

        if (! $token) {
            return;
        }

        $device = Device::query()
            ->where(function ($query) use ($token) {
                $query->where('api_token', $token)
                    ->orWhere('api_token', hash('sha256', $token));
            })
            ->when($request->filled('machine_name'), fn ($query) => $query->where('machine_name', (string) $request->input('machine_name')))
            ->first();

        if (! $device) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid device API token.');
        }
    }

    private function resolveRequestIp(Request $request): ?string
    {
        $forwardedFor = $request->header('X-Forwarded-For');
        $cloudflareIp = $request->header('CF-Connecting-IP');
        $ipAddress = $cloudflareIp
            ?: ($forwardedFor ? trim(explode(',', $forwardedFor)[0]) : $request->ip());

        return $ipAddress ? substr($ipAddress, 0, 45) : null;
    }

    private function recordRequestIpFromPayload(array $payload, string $source, ?string $ipAddress): void
    {
        if (! $ipAddress
            || ! isset($payload['employee_code'], $payload['machine_name'], $payload['device_name'])) {
            return;
        }

        $employee = $this->resolveEmployee((string) $payload['employee_code']);
        $device = $this->resolveDevice(
            $employee,
            (string) $payload['machine_name'],
            (string) $payload['device_name']
        );

        $this->recordRequestIp(
            $ipAddress,
            $employee,
            $device,
            $this->payloadReferenceTime($source, $payload),
            $source
        );
    }

    private function payloadReferenceTime(string $source, array $payload): Carbon
    {
        $value = match ($source) {
            'heartbeat' => $payload['last_seen_at'] ?? null,
            'activity_log', 'website_log' => $payload['ended_at'] ?? $payload['started_at'] ?? null,
            'session_start' => $payload['started_at'] ?? null,
            'session_end' => $payload['ended_at'] ?? null,
            'system_event' => $payload['occurred_at'] ?? null,
            default => $payload['occurred_at']
                ?? $payload['last_seen_at']
                ?? $payload['ended_at']
                ?? $payload['started_at']
                ?? null,
        };

        return $value ? Carbon::parse((string) $value) : now();
    }

    private function recordRequestIp(?string $ipAddress, Employee $employee, Device $device, Carbon $recordedAt, string $source): void
    {
        if (! $ipAddress) {
            return;
        }

        $recordedHour = $recordedAt->copy()->startOfHour();
        $existingLog = DeviceIpLog::query()
            ->where('device_id', $device->id)
            ->where('recorded_hour', $recordedHour)
            ->where('ip_address', $ipAddress)
            ->first();

        if ($existingLog) {
            $existingLog->update([
                'recorded_at' => $recordedAt,
                'source' => $source,
            ]);

            return;
        }

        $previousLog = DeviceIpLog::query()
            ->where('device_id', $device->id)
            ->latest('recorded_at')
            ->first();

        $isChanged = $previousLog !== null && $previousLog->ip_address !== $ipAddress;

        DeviceIpLog::create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'ip_address' => $ipAddress,
            'recorded_hour' => $recordedHour,
            'recorded_at' => $recordedAt,
            'source' => $source,
            'is_changed' => $isChanged,
        ]);

        if ($isChanged) {
            Notification::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'type' => 'ip_change_alert',
                'title' => 'IP address changed',
                'message' => sprintf(
                    '%s changed IP from %s to %s.',
                    $device->device_name,
                    $previousLog->ip_address,
                    $ipAddress
                ),
                'raised_at' => $recordedAt,
            ]);
        }
    }

}
