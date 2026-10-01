<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Le programme d'une classe : une matière enseignée dans une classe,
 * avec son coefficient et son volume horaire propres à cette classe.
 */
class ClasseMatiere extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classe_matiere';

    protected $fillable = [
        'classe_id',
        'matiere_id',
        'coefficient',
        'volume_horaire',
        'ordre',
    ];

    protected $casts = [
        'coefficient'    => 'integer',
        'volume_horaire' => 'integer',
        'ordre'          => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function affectation(): HasOne
    {
        return $this->hasOne(Affectation::class);
    }

    public function enseignant(): HasOne
    {
        return $this->hasOne(Affectation::class)->with('enseignant');
    }
}
