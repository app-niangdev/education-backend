<?php

namespace App\Models;

use App\Enums\CycleNiveauEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un niveau d'enseignement (Sixième, Seconde…).
 *
 * `ordre` situe le niveau DANS SON CYCLE : Sixième est le premier du collège,
 * Seconde le premier du lycée. C'est ce qui permet au module de passage de
 * classe de savoir quel niveau vient ensuite.
 *
 * Le niveau ne porte AUCUN montant : les tarifs dependent de l'annee scolaire
 * et vivent uniquement dans la grille frais_scolaires (module Frais scolaire).
 */
class Niveau extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'code',
        'ordre',
        'cycle',
    ];

    protected $casts = [
        'cycle' => CycleNiveauEnum::class,
        'ordre' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'cycle_libelle',
    ];

    public function getCycleLibelleAttribute(): ?string
    {
        return $this->cycle?->libelle();
    }

    /**
     * Le niveau qui suit dans le même cycle, s'il existe.
     *
     * Retourne null en fin de cycle (Troisième, Terminale) : le passage vers
     * le cycle suivant est une orientation, elle ne s'automatise pas.
     */
    public function niveauSuivant(): ?self
    {
        if ($this->cycle === null) {
            return null;
        }

        return self::query()
            ->where('cycle', $this->cycle)
            ->where('ordre', $this->ordre + 1)
            ->first();
    }

    /** Les niveaux d'un cycle, dans l'ordre de progression. */
    public function scopeParCycle(Builder $query, CycleNiveauEnum|string $cycle): Builder
    {
        $valeur = $cycle instanceof CycleNiveauEnum ? $cycle->value : $cycle;

        return $query->where('cycle', $valeur)->orderBy('ordre');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }

    /**
     * Le bareme du niveau, au singulier.
     *
     * La contrainte d'unicite (annee, niveau) garantit qu'il n'y en a qu'un par
     * annee : filtree sur une annee au chargement, cette relation ramene donc
     * une ligne ou rien. C'est ce que presente l'interface unifiee, un niveau
     * et son tarif sur la meme ligne.
     */
    public function fraisScolaire(): HasOne
    {
        return $this->hasOne(FraisScolaire::class);
    }
}
