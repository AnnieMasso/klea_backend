<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'contrat_id',
        'mode_paiement',
        'montant_paiement',
    ];

    protected function casts(): array
    {
        return [
            'montant_paiement' => 'integer',
        ];
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(contrat::class, 'contrat_id');
    }
}
