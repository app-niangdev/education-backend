<?php

namespace App\Models;

use App\Enums\StatutAnneeScolaire;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnneeScolaire extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * `en_cours` est volontairement absent : c'est le pivot dont dépendent
     * treize requêtes de l'application, et un index unique partiel garantit
     * qu'une seule année le porte. Sa bascule appartient à la transaction de
     * clôture (qui utilise forceFill), pas à un formulaire.
     * teste
     */
    protected $fillable = [
        'nom',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'en_cours'   => 'boolean',
        'statut'     => StatutAnneeScolaire::class,
    ];

    protected $appends = [
        'statut_libelle',
    ];

    public function getStatutLibelleAttribute(): ?string
    {
        return $this->statut?->libelle();
    }

    public function estCloturee(): bool
    {
        return $this->statut === StatutAnneeScolaire::CLOTURER;
    }

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function fraisScolaires(): HasMany
    {
        return $this->hasMany(FraisScolaire::class);
    }

    /**
     * Les trois relations suivantes sont en cascadeOnDelete en base : sans
     * elles, rien ne signalerait que supprimer l'annee emporte ces donnees.
     */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function seancesAppel(): HasMany
    {
        return $this->hasMany(SeanceAppel::class);
    }
}
