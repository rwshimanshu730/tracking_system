<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $this->refreshOfflineAlerts();

        return view('notifications.index', [
            'pageTitle' => 'Notifications',
            'notifications' => Notification::with(['employee', 'device'])->latest('raised_at')->paginate(20),
        ]);
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        $notification->update(['is_read' => true]);

        return redirect()->route('notifications.index')->with('status', 'Notification marked as read.');
    }

    public function refreshOfflineAlerts(): void
    {
        $threshold = now()->subMinutes(10);

        Device::with('employee')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $threshold)
            ->get()
            ->each(function (Device $device) {
                Notification::firstOrCreate(
                    [
                        'device_id' => $device->id,
                        'type' => 'missing_heartbeat',
                        'raised_at' => Carbon::parse($device->last_seen_at)->startOfMinute(),
                    ],
                    [
                        'employee_id' => $device->employee_id,
                        'title' => 'Missing heartbeat detected',
                        'message' => sprintf(
                            '%s has not reported for more than 10 minutes.',
                            $device->device_name
                        ),
                    ]
                );
            });
    }
}
