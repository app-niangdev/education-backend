<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'first_name', 'last_name', 'email', 'password',
    'phone_one', 'phone_two', 'username', 'address', 'status', 'role_id',
    'must_change_password', 'two_factor_enabled',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * La photo de profil, sur le disque `users`.
     *
     * `singleFile()` : deposer une nouvelle photo remplace l'ancienne et
     * supprime son fichier. Sans cela, chaque changement laisserait un
     * orphelin sur le disque, et `getFirstMediaUrl` renverrait la premiere
     * photo deposee plutot que la derniere.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION_PHOTO)
            ->singleFile()
            ->useDisk('users');
    }

    public const COLLECTION_PHOTO = 'photo';

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'status'               => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_enabled'   => 'boolean',
        ];
    }

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $appends = [
        'full_name',
        'image_url',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') . ' ' .
            ($this->last_name ?? '')
        );
    }

    /**
     * URL publique de la photo de profil, ou null si le compte n'en a pas.
     *
     * `getFirstMediaUrl` renvoie une chaine vide en l'absence de media : on la
     * normalise en null, seule valeur que le front sait interpreter comme
     * « affiche l'avatar par defaut ».
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl(self::COLLECTION_PHOTO) ?: null;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function enseignant(): HasOne
    {
        return $this->hasOne(Enseignant::class);
    }

    public function surveillant(): HasOne
    {
        return $this->hasOne(Surveillant::class);
    }

    public function tresorier(): HasOne
    {
        return $this->hasOne(Tresorier::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * La fiche tuteur rattachee a ce compte, si c'en est un. Symetrique de
     * enseignant()/surveillant()/tresorier() : chaque role metier a son profil.
     */
    public function tuteur(): HasOne
    {
        return $this->hasOne(Tuteur::class);
    }

    /** Les messages envoyes, tous fils confondus. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'expediteur_id');
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function estTuteur(): bool
    {
        return $this->role?->name === 'tuteur';
    }
}
