<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceIpLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'device_id',
        'ip_address',
        'recorded_hour',
        'recorded_at',
        'source',
        'is_changed',
    ];

    protected function casts(): array
    {
        return [
            'recorded_hour' => 'datetime',
            'recorded_at' => 'datetime',
            'is_changed' => 'boolean',
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
}
