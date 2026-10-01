<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rattache un enseignant à un couple (classe × matière).
 * Un enseignant peut avoir plusieurs affectations (plusieurs matières / classes).
 */
class Affectation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'affectations';

    protected $fillable = [
        'enseignant_id',
        'classe_matiere_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    /**
     * Les évaluations créées par l'enseignant pour ce couple (classe × matière).
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
