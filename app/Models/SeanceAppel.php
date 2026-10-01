<?php

namespace App\Models;

use App\Enums\StatutSeanceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un créneau de l'emploi du temps, tenu à une date précise.
 *
 * Les horaires et la durée sont figés à la création : ils décrivent la séance
 * telle qu'elle a eu lieu, pas le créneau tel qu'il est aujourd'hui. Ne jamais
 * les relire depuis `creneau` à l'affichage.
 */
class SeanceAppel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'seances_appel';

    protected $fillable = [
        'emploi_du_temps_id',
        'classe_id',
        'affectation_id',
        'annee_scolaire_id',
        'periode_id',
        'date_seance',
        'heure_debut',
        'heure_fin',
        'duree_minutes',
        'statut',
        'saisie_par',
        'saisie_le',
        'commentaire',
    ];

    protected $casts = [
        'date_seance'   => 'date',
        'duree_minutes' => 'integer',
        'statut'        => StatutSeanceEnum::class,
        'saisie_le'     => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $attributes = [
        'statut' => StatutSeanceEnum::FAITE->value,
    ];

    protected $appends = [
        'statut_libelle',
        'matiere_nom',
        'enseignant_nom',
    ];

    public function getStatutLibelleAttribute(): string
    {
        return $this->statut instanceof StatutSeanceEnum
            ? $this->statut->libelle()
            : (string) $this->statut;
    }

    public function getMatiereNomAttribute(): ?string
    {
        return $this->affectation?->classeMatiere?->matiere?->nom;
    }

    public function getEnseignantNomAttribute(): ?string
    {
        $user = $this->affectation?->enseignant?->user;

        return $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null;
    }

    public function creneau(): BelongsTo
    {
        return $this->belongsTo(EmploiDuTemps::class, 'emploi_du_temps_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    /** Les anomalies relevées : un élève présent n'a pas de ligne. */
    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }
}
