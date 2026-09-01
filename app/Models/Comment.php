<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'sender_type',
        'sender_id',
        'author_name',
        'parent_id',
        'recipient_type',
        'recipient_id',
        'body',
        'role',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }



    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
