<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class condition extends Model
{
    protected $table = 'conditions';

    protected $fillable = [
        'contrat_id',
        'description',
    ];

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(contrat::class, 'contrat_id');
    }
}
