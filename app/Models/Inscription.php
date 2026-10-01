<?php

namespace App\Models;

use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use App\Enums\TypeInscriptionEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inscriptions';

    protected $fillable = [
        'numero_inscription',
        'eleve_id',
        'classe_id',
        'annee_scolaire_id',
        'type_inscription',
        'utilisateur_id',
        'montant_inscription',
        'statut_inscription',
        'statut_paiement',
        'date_inscription',
    ];

    protected $casts = [
        'montant_inscription' => 'integer',
        'type_inscription'    => TypeInscriptionEnum::class,
        'statut_inscription'  => StatutInscriptionEnum::class,
        'statut_paiement'     => StatutPaiementEnum::class,
        'date_inscription'    => 'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'montant_inscription_paye',
        'montant_inscription_restant',
    ];

    /**
     * Reprend les valeurs par defaut de la migration afin qu'une instance
     * fraichement creee expose deja ses statuts (et non null).
     */
    protected $attributes = [
        'type_inscription'   => TypeInscriptionEnum::NOUVELLE->value,
        'statut_inscription' => StatutInscriptionEnum::EN_ATTENTE->value,
        'statut_paiement'    => StatutPaiementEnum::NON_PAYE->value,
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function mensualites(): HasMany
    {
        return $this->hasMany(Mensualite::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementInscription::class);
    }

    /** Factures de mensualites emises pour cet eleve (un ou plusieurs mois). */
    public function facturesMensualites(): HasMany
    {
        return $this->hasMany(FactureMensualite::class);
    }

    /**
     * Somme des paiements. Utilise la relation deja chargee si disponible
     * pour eviter une requete par modele.
     */
    public function getMontantInscriptionPayeAttribute(): int
    {
        if ($this->relationLoaded('paiements')) {
            return (int) $this->paiements->sum('montant');
        }

        return (int) $this->paiements()->sum('montant');
    }

    public function getMontantInscriptionRestantAttribute(): int
    {
        return max(0, $this->montant_inscription - $this->montant_inscription_paye);
    }

    /**
     * Statut derive des paiements, sans stocker de montant cumule.
     */
    public function statutPaiementCalcule(): StatutPaiementEnum
    {
        $totalPaye = $this->montant_inscription_paye;

        return match (true) {
            $totalPaye <= 0                            => StatutPaiementEnum::NON_PAYE,
            $totalPaye >= $this->montant_inscription   => StatutPaiementEnum::PAYE,
            default                                    => StatutPaiementEnum::PARTIEL,
        };
    }

    /**
     * Recalcule et persiste statut_paiement a partir des paiements reels.
     */
    public function synchroniserStatutPaiement(): void
    {
        $this->forceFill([
            'statut_paiement' => $this->statutPaiementCalcule(),
        ])->save();
    }

    public function estAnnulee(): bool
    {
        return $this->statut_inscription === StatutInscriptionEnum::ANNULEE;
    }

    public function estValidee(): bool
    {
        return $this->statut_inscription === StatutInscriptionEnum::VALIDEE;
    }

    public function estEnAttente(): bool
    {
        return $this->statut_inscription === StatutInscriptionEnum::EN_ATTENTE;
    }

    public function scopeSolde(Builder $query): Builder
    {
        return $query->where('statut_paiement', StatutPaiementEnum::PAYE);
    }

    public function scopeNonSolde(Builder $query): Builder
    {
        return $query->whereIn('statut_paiement', [
            StatutPaiementEnum::NON_PAYE,
            StatutPaiementEnum::PARTIEL,
        ]);
    }
}
