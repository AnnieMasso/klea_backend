<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class conversation extends Model
{
    protected $table = 'conversations';

    protected $fillable = [
        'user_id',
        'bien_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(bien::class, 'bien_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(message::class, 'conversation_id');
    }

    public function contrat(): HasOne
    {
        return $this->hasOne(contrat::class, 'conversation_id');
    }
}
