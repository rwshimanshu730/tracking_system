<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Employee;
use App\Models\ProductivityRule;
use App\Models\User;
use App\Models\WebsiteLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_payloads_are_saved_and_visible_to_the_dashboard(): void
    {
        $this->postJson('/api/devices/register', [
            'employee_code' => 'EMP-001',
            'device_name' => 'Primary Workstation',
            'machine_name' => 'DESKTOP-001',
            'os_name' => 'Windows 11',
            'agent_version' => '0.1.0',
        ])->assertOk();

        $this->postJson('/api/sessions/start', [
            'employee_code' => 'EMP-001',
            'device_name' => 'Primary Workstation',
            'machine_name' => 'DESKTOP-001',
            'started_at' => now()->toIso8601String(),
            'event_type' => 'session_start',
        ])->assertOk();

        $this->postJson('/api/sync/batch', [
            'events' => [
                [
                    'type' => 'heartbeat',
                    'payload' => [
                        'employee_code' => 'EMP-001',
                        'device_name' => 'Primary Workstation',
                        'machine_name' => 'DESKTOP-001',
                        'last_seen_at' => now()->toIso8601String(),
                        'is_online' => true,
                    ],
                ],
                [
                    'type' => 'system_event',
                    'payload' => [
                        'employee_code' => 'EMP-001',
                        'device_name' => 'Primary Workstation',
                        'machine_name' => 'DESKTOP-001',
                        'event_type' => 'startup',
                        'occurred_at' => now()->toIso8601String(),
                        'payload' => ['source' => 'test'],
                    ],
                ],
                [
                    'type' => 'activity_log',
                    'payload' => [
                        'employee_code' => 'EMP-001',
                        'device_name' => 'Primary Workstation',
                        'machine_name' => 'DESKTOP-001',
                        'app_name' => 'Code',
                        'window_title' => 'tracking_agent\\runner.py',
                        'started_at' => now()->subSeconds(30)->toIso8601String(),
                        'ended_at' => now()->toIso8601String(),
                        'activity_type' => 'active_window',
                        'is_productive' => true,
                        'duration_seconds' => 30,
                        'recorded_on' => now()->toDateString(),
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('work_sessions', 1);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseCount('system_events', 2);

        $this->postJson('/api/sessions/end', [
            'employee_code' => 'EMP-001',
            'device_name' => 'Primary Workstation',
            'machine_name' => 'DESKTOP-001',
            'ended_at' => now()->toIso8601String(),
            'event_type' => 'session_end',
        ])->assertOk();

        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('EMP-001')
            ->assertSee('Code');

        $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertSee('System Startup and Shutdown');
    }

    public function test_phase_three_features_cover_rules_manual_entries_and_exports(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $employee = Employee::create([
            'employee_code' => 'EMP-002',
            'name' => 'Amit Sharma',
            'employment_status' => 'active',
        ]);

        $device = Device::create([
            'employee_id' => $employee->id,
            'device_name' => 'Manager Workstation',
            'machine_name' => 'DESKTOP-002',
            'api_token' => hash('sha256', 'secret-token'),
            'is_online' => true,
        ]);

        ProductivityRule::create([
            'match_type' => 'app_name',
            'match_value' => 'code',
            'category' => 'Development',
            'productivity_type' => 'productive',
            'is_active' => true,
        ]);

        $this->postJson('/api/activity-logs', [
            'employee_code' => 'EMP-002',
            'device_name' => $device->device_name,
            'machine_name' => $device->machine_name,
            'app_name' => 'Code',
            'window_title' => 'phase3.txt',
            'started_at' => now()->subMinutes(5)->toIso8601String(),
            'ended_at' => now()->subMinutes(1)->toIso8601String(),
            'activity_type' => 'active_window',
            'duration_seconds' => 240,
            'keyboard_events' => 28,
            'mouse_events' => 11,
            'recorded_on' => now()->toDateString(),
        ], [
            'Authorization' => 'Bearer secret-token',
        ])->assertCreated();

        $this->actingAs($manager)->post('/manual-time', [
            'employee_id' => $employee->id,
            'entry_date' => now()->toDateString(),
            'minutes' => 45,
            'entry_type' => 'meeting',
            'reason' => 'Client call',
        ])->assertRedirect(route('manual-time.index'));

        $this->assertDatabaseHas('activity_logs', [
            'employee_id' => $employee->id,
            'app_name' => 'Code',
            'category' => 'Development',
            'is_productive' => true,
        ]);

        $this->assertDatabaseHas('manual_time_entries', [
            'employee_id' => $employee->id,
            'minutes' => 45,
            'entry_type' => 'meeting',
        ]);

        $this->actingAs($manager)->get('/productivity-rules')
            ->assertOk()
            ->assertSee('Productivity Rules')
            ->assertSee('Development');

        $this->actingAs($manager)->get('/manual-time')
            ->assertOk()
            ->assertSee('Manual Time Entries')
            ->assertSee('Client call');

        $this->actingAs($manager)->get('/reports')
            ->assertOk()
            ->assertSee('Manual Time CSV')
            ->assertSee('Development')
            ->assertSee('Meeting')
            ->assertSee('Amit Sharma');

        $this->actingAs($manager)->get('/reports/export/apps')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($manager)->get('/reports/export/manual-time')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_phase_four_website_tracking_is_saved_and_visible_in_reports(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $employee = Employee::create([
            'employee_code' => 'EMP-004',
            'name' => 'Riya Kapoor',
            'employment_status' => 'active',
        ]);

        $device = Device::create([
            'employee_id' => $employee->id,
            'device_name' => 'Browser Laptop',
            'machine_name' => 'DESKTOP-004',
            'api_token' => hash('sha256', 'phase4-token'),
            'is_online' => true,
        ]);

        ProductivityRule::create([
            'match_type' => 'domain',
            'match_value' => 'github.com',
            'category' => 'Development',
            'productivity_type' => 'productive',
            'is_active' => true,
        ]);

        $this->postJson('/api/website-logs', [
            'employee_code' => 'EMP-004',
            'device_name' => $device->device_name,
            'machine_name' => $device->machine_name,
            'browser_name' => 'Chrome',
            'page_title' => 'Pull Request',
            'url' => 'https://github.com/example/repo/pull/1',
            'domain' => 'github.com',
            'started_at' => now()->subMinutes(10)->toIso8601String(),
            'ended_at' => now()->subMinutes(5)->toIso8601String(),
            'duration_seconds' => 300,
            'recorded_on' => now()->toDateString(),
        ], [
            'Authorization' => 'Bearer phase4-token',
        ])->assertCreated();

        $this->assertDatabaseCount('website_logs', 1);
        $this->assertDatabaseHas('website_logs', [
            'employee_id' => $employee->id,
            'browser_name' => 'Chrome',
            'domain' => 'github.com',
            'category' => 'Development',
        ]);

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('Top Domains Today')
            ->assertSee('github.com');

        $this->actingAs($manager)->get('/reports')
            ->assertOk()
            ->assertSee('Top Domains Today')
            ->assertSee('Website Category Totals')
            ->assertSee('github.com')
            ->assertSee('Development');

        $this->assertSame(1, WebsiteLog::count());
    }
}
