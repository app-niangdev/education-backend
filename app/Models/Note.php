<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La note d'un élève à une évaluation. `valeur` est exprimée sur le barème
 * de l'évaluation. Lorsque l'élève est `absent`, `valeur` peut rester nulle.
 */
class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notes';

    protected $fillable = [
        'evaluation_id',
        'eleve_id',
        'valeur',
        'absent',
        'appreciation',
    ];

    protected $casts = [
        'valeur' => 'decimal:2',
        'absent' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }
}
