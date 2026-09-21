<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class image extends Model
{
    protected $table = 'images';

    protected $fillable = [
        'bien_id',
        'slug',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(bien::class, 'bien_id');
    }
}
