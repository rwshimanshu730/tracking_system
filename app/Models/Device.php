<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_name',
        'machine_name',
        'os_name',
        'agent_version',
        'api_token',
        'last_seen_at',
        'is_online',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_online' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function systemEvents(): HasMany
    {
        return $this->hasMany(SystemEvent::class);
    }

    public function ipLogs(): HasMany
    {
        return $this->hasMany(DeviceIpLog::class);
    }
}
