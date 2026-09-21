<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class bien extends Model
{
    protected $table = 'biens';

    protected $fillable = [
        'bailleur_id',
        'titre',
        'type',
        'description',
        'montant',
        'modalité_paiement',
        'statut',
        'superficie',
        'nb_chambres',
        'nb_douches',
        'ammeublement',
        'ville',
        'quartier',
        'lieu_dit',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'superficie' => 'integer',
            'nb_chambres' => 'integer',
            'nb_douches' => 'integer',
            'ammeublement' => 'boolean',
        ];
    }

    public function bailleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bailleur_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(image::class, 'bien_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(usage::class, 'bien_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(conversation::class, 'bien_id');
    }
}
