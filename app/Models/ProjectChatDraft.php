<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectChatDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'sender_type',
        'sender_id',
        'body',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
