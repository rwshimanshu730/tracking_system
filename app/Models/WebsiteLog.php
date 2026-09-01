<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'work_session_id',
        'browser_name',
        'page_title',
        'url',
        'domain',
        'category',
        'is_productive',
        'started_at',
        'ended_at',
        'duration_seconds',
        'recorded_on',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_productive' => 'boolean',
            'recorded_on' => 'date',
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

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }
}
