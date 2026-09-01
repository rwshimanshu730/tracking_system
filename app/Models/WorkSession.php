<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'started_at',
        'ended_at',
        'login_at',
        'logout_at',
        'active_seconds',
        'idle_seconds',
        'productivity_score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
            'productivity_score' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function systemEvents(): HasMany
    {
        return $this->hasMany(SystemEvent::class);
    }

    public function websiteLogs(): HasMany
    {
        return $this->hasMany(WebsiteLog::class);
    }
}
