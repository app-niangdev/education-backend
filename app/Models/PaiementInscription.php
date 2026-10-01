<?php

namespace App\Models;

use App\Enums\ModePaiementEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaiementInscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'paiement_inscriptions';

    protected $fillable = [
        'inscription_id',
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

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
