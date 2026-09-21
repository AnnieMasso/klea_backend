<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class depart extends Model
{
    protected $table = 'departs';

    protected $fillable = [
        'contrat_id',
        'date_depart_prevue',
        'date_confirmation',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_depart_prevue' => 'date',
            'date_confirmation' => 'date',
            'statut' => 'boolean',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(contrat::class, 'contrat_id');
    }
}
