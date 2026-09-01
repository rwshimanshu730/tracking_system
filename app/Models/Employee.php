<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'name',
        'email',
		'password',
        'department',
        'designation',
        'employment_status',
        'joined_on',
    ];
	
	  protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
			'password' => 'hashed',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array('user', $roles, true);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
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
