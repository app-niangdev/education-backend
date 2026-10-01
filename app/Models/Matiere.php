<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matiere extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'matieres';

    protected $fillable = [
        'nom',
        'code',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function classeMatieres(): HasMany
    {
        return $this->hasMany(ClasseMatiere::class);
    }

    /**
     * Les classes où cette matière est enseignée. Le coefficient et le volume
     * horaire propres à chaque classe sont exposés via le pivot.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_matiere')
            ->withPivot(['coefficient', 'volume_horaire'])
            ->withTimestamps();
    }

    /**
     * Les enseignants qualifiés pour cette matière (leur spécialité), qu'ils
     * y soient affectés ou non dans une classe donnée.
     */
    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(Enseignant::class, 'enseignant_matiere')
            ->withTimestamps();
    }
}
