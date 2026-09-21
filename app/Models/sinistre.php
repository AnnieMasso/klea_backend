<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class sinistre extends Model
{
    protected $table = 'sinistres';

    protected $fillable = [
        'contrat_id',
        'date_sinistre',
        'description',
        'img1',
        'img2',
    ];

    protected function casts(): array
    {
        return [
            'date_sinistre' => 'date',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(contrat::class, 'contrat_id');
    }
}
