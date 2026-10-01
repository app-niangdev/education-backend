<?php

namespace App\Models;

use App\Enums\ModePaiementEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entete d'un encaissement couvrant un ou plusieurs mois. Le detail par mois
 * reste porte par les PaiementMensualite rattaches : la facture ne duplique
 * aucun montant, elle totalise ses lignes.
 */
class FactureMensualite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facture_mensualites';

    protected $fillable = [
        'inscription_id',
        'utilisateur_id',
        'numero_facture',
        'montant_total',
        'mode_paiement',
        'numero_transaction',
        'date_paiement',
    ];

    protected $casts = [
        'montant_total' => 'integer',
        'mode_paiement' => ModePaiementEnum::class,
        'date_paiement' => 'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'nombre_mois',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(PaiementMensualite::class, 'facture_mensualite_id');
    }

    /**
     * Nombre de mois couverts. Utilise la relation deja chargee si disponible
     * pour eviter une requete par modele dans les listes.
     */
    public function getNombreMoisAttribute(): int
    {
        if ($this->relationLoaded('lignes')) {
            return $this->lignes->count();
        }

        return $this->lignes()->count();
    }
}
