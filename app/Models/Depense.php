<?php

namespace App\Models;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use App\Enums\StatutDepenseEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une depense de l'etablissement, rattachee a une annee scolaire.
 */
class Depense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'depenses';

    protected $fillable = [
        'annee_scolaire_id',
        'utilisateur_id',
        'libelle',
        'categorie',
        'montant',
        'mode_paiement',
        'beneficiaire',
        'reference',
        'date_depense',
        'description',
        // statut, validateur_id, valide_le et motif_refus sont volontairement
        // absents : ils ne se saisissent pas, ils resultent d'une decision que
        // seul DepenseService::valider() / refuser() a le droit d'inscrire.
    ];

    protected $casts = [
        'montant'       => 'integer',
        'categorie'     => CategorieDepenseEnum::class,
        'mode_paiement' => ModePaiementEnum::class,
        'date_depense'  => 'date',
        'statut'        => StatutDepenseEnum::class,
        'valide_le'     => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'categorie_libelle',
        'mode_paiement_libelle',
        'statut_libelle',
    ];

    public function getCategorieLibelleAttribute(): ?string
    {
        return $this->categorie?->libelle();
    }

    public function getModePaiementLibelleAttribute(): ?string
    {
        return $this->mode_paiement?->libelle();
    }

    public function getStatutLibelleAttribute(): ?string
    {
        return $this->statut?->libelle();
    }

    /**
     * Les depenses effectivement engagees. C'est le filtre des totaux et du
     * bilan : une depense en attente ou refusee n'est pas une sortie de caisse.
     */
    public function scopeComptabilisees(Builder $query): Builder
    {
        return $query->where('statut', StatutDepenseEnum::VALIDEE);
    }

    /** Celles qui attendent la decision d'un manager. */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', StatutDepenseEnum::EN_ATTENTE);
    }

    public function estComptabilisee(): bool
    {
        return $this->statut?->estComptabilisee() ?? false;
    }

    /** Depenses d'une journee precise. */
    public function scopeDuJour(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_depense', $date);
    }

    /**
     * Depenses d'un intervalle. Les bornes sont incluses, et chacune est
     * facultative : un intervalle ouvert d'un cote reste un filtre valide.
     */
    public function scopeEntreDates(Builder $query, ?string $debut, ?string $fin): Builder
    {
        return $query
            ->when($debut, fn ($q, $v) => $q->whereDate('date_depense', '>=', $v))
            ->when($fin,   fn ($q, $v) => $q->whereDate('date_depense', '<=', $v));
    }

    /** Depenses d'un mois calendaire (1-12) d'une annee civile donnee. */
    public function scopeDuMois(Builder $query, int $mois, int $annee): Builder
    {
        return $query
            ->whereMonth('date_depense', $mois)
            ->whereYear('date_depense', $annee);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /** Le manager (ou l'admin) qui a tranche. Nul tant que rien n'est decide. */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }
}
