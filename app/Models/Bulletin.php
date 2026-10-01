<?php

namespace App\Models;

use App\Enums\DecisionConseilEnum;
use App\Enums\DistinctionEnum;
use App\Enums\MentionEnum;
use App\Enums\StatutBulletinEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Le bulletin d'un élève pour une période.
 *
 * Les colonnes `eleve_*`, `classe_nom` et `effectif_classe` dupliquent des
 * données accessibles par les relations : c'est voulu. Elles constituent la
 * photographie du bulletin au moment de sa génération, et ce sont elles —
 * jamais les relations — qui doivent être imprimées. Les relations ne servent
 * qu'à la navigation et aux filtres.
 */
class Bulletin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bulletins';

    protected $fillable = [
        'eleve_id',
        'classe_id',
        'periode_id',
        'annee_scolaire_id',
        'statut',
        'eleve_nom',
        'eleve_prenom',
        'eleve_matricule',
        'eleve_date_naissance',
        'eleve_lieu_naissance',
        'classe_nom',
        'classe_redoublee',
        'effectif_classe',
        'total_coefficients',
        'total_points',
        'moyenne_generale',
        'rang',
        'rang_ex_aequo',
        'mention',
        'decision_conseil',
        'distinction',
        'observations',
        'retards',
        'absences',
        'publie_par',
        'publie_le',
        'genere_le',
    ];

    protected $casts = [
        'statut'               => StatutBulletinEnum::class,
        'mention'              => MentionEnum::class,
        'decision_conseil'     => DecisionConseilEnum::class,
        'distinction'          => DistinctionEnum::class,
        'eleve_date_naissance' => 'date',
        'classe_redoublee'     => 'boolean',
        'rang_ex_aequo'        => 'boolean',
        'effectif_classe'      => 'integer',
        'total_coefficients'   => 'integer',
        'total_points'         => 'decimal:2',
        'moyenne_generale'     => 'decimal:2',
        'rang'                 => 'integer',
        'retards'              => 'integer',
        'absences'             => 'integer',
        'publie_le'            => 'datetime',
        'genere_le'            => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $attributes = [
        'statut' => StatutBulletinEnum::BROUILLON->value,
    ];

    protected $appends = [
        'nom_complet',
        'statut_libelle',
        'mention_libelle',
        'decision_conseil_libelle',
        'distinction_libelle',
        'est_publie',
    ];

    /** Le nom tel qu'il était à la génération, pas celui de l'élève aujourd'hui. */
    public function getNomCompletAttribute(): string
    {
        return trim(($this->eleve_prenom ?? '') . ' ' . ($this->eleve_nom ?? ''));
    }

    public function getStatutLibelleAttribute(): string
    {
        return $this->statut instanceof StatutBulletinEnum
            ? $this->statut->libelle()
            : (string) $this->statut;
    }

    public function getMentionLibelleAttribute(): ?string
    {
        return $this->mention?->libelle();
    }

    public function getDecisionConseilLibelleAttribute(): ?string
    {
        return $this->decision_conseil?->libelle();
    }

    public function getDistinctionLibelleAttribute(): ?string
    {
        return $this->distinction?->libelle();
    }

    public function getEstPublieAttribute(): bool
    {
        return $this->estPublie();
    }

    public function estPublie(): bool
    {
        return $this->statut === StatutBulletinEnum::PUBLIE;
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    /** Les lignes du bulletin, dans l'ordre d'impression. */
    public function lignes(): HasMany
    {
        return $this->hasMany(BulletinLigne::class)->orderBy('ordre');
    }
}
