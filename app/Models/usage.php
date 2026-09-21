<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class usage extends Model
{
    protected $table = 'usages';

    protected $fillable = [
        'bien_id',
        'nom_usage',
    ];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(bien::class, 'bien_id');
    }
}
