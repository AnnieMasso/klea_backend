<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'role',
        'mot_de_passe',
        'statut',
        'cni_recto',
        'cni_verso',
        'assurance_habitation',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mot_de_passe' => 'hashed',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->mot_de_passe;
    }

    public function biens(): HasMany
    {
        return $this->hasMany(bien::class, 'bailleur_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(conversation::class, 'user_id');
    }

    public function signalementsEnTantQuePlaignant(): HasMany
    {
        return $this->hasMany(signalement::class, 'plaignant_id');
    }

    public function signalementsEnTantQueAccuse(): HasMany
    {
        return $this->hasMany(signalement::class, 'accuse_id');
    }

}