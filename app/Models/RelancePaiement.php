<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une tentative de relance WhatsApp d'un tuteur en retard de paiement.
 */
class RelancePaiement extends Model
{
    public const ENVOYEE = 'ENVOYEE';
    public const ECHEC   = 'ECHEC';

    /** Arriere reclame par le tresorier. */
    public const RELANCE = 'RELANCE';

    /** Echeance a venir, rappelee par le planificateur. */
    public const RAPPEL = 'RAPPEL';

    protected $table = 'relances_paiement';

    protected $fillable = [
        'tuteur_id',
        'utilisateur_id',
        'type',
        'montant',
        'telephone',
        'statut',
        'erreur',
    ];

    protected $casts = [
        'montant' => 'integer',
    ];

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(Tuteur::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
