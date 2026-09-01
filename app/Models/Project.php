<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer;
use App\Models\User;
use App\Models\Comment;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'created_by',
        'start_date',
        'due_date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('member_role')
            ->withTimestamps();
    }

    public function employeeMembers(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_members', 'project_id', 'employee_id')
            ->withPivot('member_role')
            ->withTimestamps();
    }

    public function customerMembers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'project_members', 'project_id', 'customer_id')
            ->wherePivot('member_role', 'customer')
            ->withPivot('member_role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function bugs(): HasMany
    {
        return $this->hasMany(ProjectBug::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'asc');
    }
}
