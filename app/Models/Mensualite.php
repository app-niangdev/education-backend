<?php

namespace App\Models;

use App\Enums\StatutPaiementEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mensualite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mensualites';

    protected $fillable = [
        'inscription_id',
        'mois',
        'annee',
        'date_echeance',
        'montant_mensualite',
        'statut',
    ];

    protected $casts = [
        'mois'               => 'integer',
        'annee'              => 'integer',
        'date_echeance'      => 'date',
        'montant_mensualite' => 'integer',
        'statut'             => StatutPaiementEnum::class,
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'total_paye',
        'reste',
    ];

    /**
     * Reprend la valeur par defaut de la migration afin qu'une instance
     * fraichement creee expose deja son statut (et non null).
     */
    protected $attributes = [
        'statut' => StatutPaiementEnum::NON_PAYE->value,
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementMensualite::class);
    }

    /**
     * Somme des paiements. Utilise la relation deja chargee si disponible
     * pour eviter une requete par modele.
     */
    public function getTotalPayeAttribute(): int
    {
        if ($this->relationLoaded('paiements')) {
            return (int) $this->paiements->sum('montant');
        }

        return (int) $this->paiements()->sum('montant');
    }

    public function getResteAttribute(): int
    {
        return max(0, $this->montant_mensualite - $this->total_paye);
    }

    /**
     * Statut derive des paiements, sans stocker de montant cumule.
     */
    public function statutCalcule(): StatutPaiementEnum
    {
        $totalPaye = $this->total_paye;

        return match (true) {
            $totalPaye <= 0                          => StatutPaiementEnum::NON_PAYE,
            $totalPaye >= $this->montant_mensualite  => StatutPaiementEnum::PAYE,
            default                                  => StatutPaiementEnum::PARTIEL,
        };
    }

    /**
     * Recalcule et persiste le statut a partir des paiements reels.
     */
    public function synchroniserStatut(): void
    {
        $this->forceFill([
            'statut' => $this->statutCalcule(),
        ])->save();
    }

    public function scopeSolde(Builder $query): Builder
    {
        return $query->where('statut', StatutPaiementEnum::PAYE);
    }

    public function scopeNonSolde(Builder $query): Builder
    {
        return $query->whereIn('statut', [
            StatutPaiementEnum::NON_PAYE,
            StatutPaiementEnum::PARTIEL,
        ]);
    }
}
