<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $devices = Device::with('employee')
            ->withCount('workSessions')
            ->latest('last_seen_at')
            ->paginate(12);

        return view('devices.index', [
            'pageTitle' => 'Manage Devices',
            'devices' => $devices,
        ]);
    }

    public function edit(Device $device): View
    {
        return view('devices.form', [
            'pageTitle' => 'Edit Device',
            'device' => $device->load(['employee', 'workSessions' => fn ($query) => $query->latest('started_at')->limit(8)]),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $payload = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'device_name' => ['required', 'string', 'max:255'],
            'os_name' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
        ]);

        $payload['is_online'] = $request->boolean('is_online');
        $device->update($payload);

        return redirect()->route('devices.index')->with('status', 'Device updated successfully.');
    }
}
