<?php

namespace App\Models;

use App\Enums\AptitudeSportiveEnum;
use App\Enums\GroupeSanguinEnum;
use App\Enums\LienParenteEnum;
use App\Enums\SexeEnum;
use App\Enums\StatutInscriptionEleveEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Eleve extends Model
{
    use HasFactory, SoftDeletes;

    public const NATIONALITE_PAR_DEFAUT = 'Sénégalaise';

    protected $table = 'eleves';

    /**
     * date_inscription est volontairement absente : elle marque la premiere
     * inscription dans l'etablissement et n'est ecrite que par le backend.
     * De meme, classe_actuelle_id est derivee des inscriptions.
     */
    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'nationalite',
        'adresse',
        'telephone',
        'photo',

        // Antecedents medicaux
        'groupe_sanguin',
        'allergies',
        'maladies_chroniques',
        'aptitude_sportive',
        'consignes_urgence',

        // Scolarite
        'statut_inscription',
        'etablissement_origine',

        // Pere
        'nom_pere',
        'prenom_pere',
        'telephone_pere',
        'profession_pere',
        'adresse_pere',

        // Mere
        'nom_mere',
        'prenom_mere',
        'telephone_mere',
        'profession_mere',
        'adresse_mere',

        'tuteur_id',
    ];

    protected $casts = [
        'sexe'               => SexeEnum::class,
        'date_naissance'     => 'date',
        'date_inscription'   => 'date',
        'groupe_sanguin'     => GroupeSanguinEnum::class,
        'aptitude_sportive'  => AptitudeSportiveEnum::class,
        'statut_inscription' => StatutInscriptionEleveEnum::class,
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Reprend les valeurs par defaut de la migration afin qu'une instance
     * fraichement creee expose deja ces attributs (et non null).
     */
    protected $attributes = [
        'nationalite'        => self::NATIONALITE_PAR_DEFAUT,
        'aptitude_sportive'  => AptitudeSportiveEnum::APTE->value,
        'statut_inscription' => StatutInscriptionEleveEnum::NOUVEAU->value,
    ];

    protected $appends = [
        'nom_complet',
        'age',
    ];

    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_naissance?->age;
    }

    public function estTransfere(): bool
    {
        return $this->statut_inscription === StatutInscriptionEleveEnum::TRANSFERE;
    }

    /**
     * Le tuteur est « distinct » lorsqu'il n'est ni le pere ni la mere :
     * l'eleve vit alors chez un tiers, et le bloc tuteur fait foi seul.
     */
    public function tuteurEstUnTiers(): bool
    {
        return $this->tuteur !== null
            && !in_array($this->tuteur->lien_parente, [LienParenteEnum::PERE, LienParenteEnum::MERE], true);
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function classeActuelle(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_actuelle_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function scopeTransferes(Builder $query): Builder
    {
        return $query->where('statut_inscription', StatutInscriptionEleveEnum::TRANSFERE);
    }

    public function scopeDansClasse(Builder $query, int|string $classeId): Builder
    {
        return $query->where('classe_actuelle_id', $classeId);
    }
}
