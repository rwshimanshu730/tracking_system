<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrivateMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_type',
        'sender_id',
        'recipient_type',
        'recipient_id',
        'body',
        'role',
    ];

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
