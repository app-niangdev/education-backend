<?php

namespace App\Models;

use App\Enums\ModePaiementEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaiementMensualite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paiement_mensualites';

    protected $fillable = [
        'mensualite_id',
        'facture_mensualite_id',
        'utilisateur_id',
        'numero_recu',
        'montant',
        'mode_paiement',
        'numero_transaction',
        'date_paiement',
    ];

    protected $casts = [
        'montant'       => 'integer',
        'mode_paiement' => ModePaiementEnum::class,
        'date_paiement' => 'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function mensualite(): BelongsTo
    {
        return $this->belongsTo(Mensualite::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /**
     * Facture qui regroupe ce versement avec les autres mois regles au meme
     * moment. Null pour un encaissement mois par mois, qui reste autonome.
     */
    public function facture(): BelongsTo
    {
        return $this->belongsTo(FactureMensualite::class, 'facture_mensualite_id');
    }
}
