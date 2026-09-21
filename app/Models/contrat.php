<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class contrat extends Model
{
    protected $table = 'contrats';

    protected $fillable = [
        'conversation_id',
        'type',
        'etat',
        'date_resiliation',
        'date_signature',
        'date_annulation',
        'montant_loyer',
        'duree',
    ];

    protected function casts(): array
    {
        return [
            'date_resiliation' => 'date',
            'date_signature' => 'date',
            'date_annulation' => 'date',
            'montant_loyer' => 'integer',
            'duree' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(conversation::class, 'conversation_id');
    }

    public function depart(): HasOne
    {
        return $this->hasOne(depart::class, 'contrat_id');
    }

    public function sinistres(): HasMany
    {
        return $this->hasMany(sinistre::class, 'contrat_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(paiement::class, 'contrat_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(condition::class, 'contrat_id');
    }
}
