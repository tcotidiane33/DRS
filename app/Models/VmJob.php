<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmJob extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'node',
        'vmid',
        'status',
        'progress',
        'message',
        'params',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
