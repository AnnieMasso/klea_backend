<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class signalement extends Model
{
    protected $table = 'signalements';

    protected $fillable = [
        'plaignant_id',
        'accuse_id',
        'motif',
        'preuve1',
        'preuve2',
    ];

    public function plaignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plaignant_id');
    }

    public function accuse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accuse_id');
    }
}
