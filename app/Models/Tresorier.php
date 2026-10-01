<?php

namespace App\Models;

use App\Models\Concerns\HasContrats;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'matricule',
    'numero_compte_bancaire', 'banque', 'acces_caisse',
])]
#[Hidden(['created_at', 'updated_at', 'deleted_at'])]
class Tresorier extends Model
{
    use HasFactory, SoftDeletes, HasContrats;

    protected $table = 'tresoriers';

    /**
     * Les conditions d'engagement sont servies par le contrat en cours (voir
     * HasContrats) : elles restent lisibles ici sans y etre stockees.
     */
    protected $appends = [
        'type_contrat',
        'date_embauche',
        'salaire_base',
        'mode_remuneration',
        'remuneration_libelle',
    ];

    protected function casts(): array
    {
        return [
            'acces_caisse' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
