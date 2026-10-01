<?php

namespace App\Models;

use App\Enums\TypeEvaluationEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une évaluation appartient à une affectation (enseignant × classe × matière)
 * et à une période. Le barème et le coefficient lui sont propres ; les notes
 * des élèves vivent dans la relation `notes`.
 */
class Evaluation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evaluations';

    /**
     * Le coefficient n'est plus stocké ici : il est déduit de la matière dans
     * la classe (classe_matiere.coefficient) et exposé via l'accessor.
     */
    protected $fillable = [
        'affectation_id',
        'periode_id',
        'titre',
        'type',
        'bareme',
        'date_evaluation',
    ];

    protected $casts = [
        'type'            => TypeEvaluationEnum::class,
        'bareme'          => 'integer',
        'date_evaluation' => 'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $attributes = [
        'type' => TypeEvaluationEnum::DEVOIR_1->value,
    ];

    protected $appends = [
        'coefficient',
        'type_libelle',
    ];

    /** Le coefficient de la matière dans la classe (via l'affectation). */
    public function getCoefficientAttribute(): ?int
    {
        return $this->affectation?->classeMatiere?->coefficient;
    }

    public function getTypeLibelleAttribute(): string
    {
        return $this->type instanceof TypeEvaluationEnum
            ? $this->type->libelle()
            : (string) $this->type;
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
