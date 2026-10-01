<?php

namespace App\Models;

use App\Models\Concerns\HasContrats;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'matricule', 'diplomes'])]
#[Hidden(['created_at', 'updated_at', 'deleted_at'])]
class Enseignant extends Model
{
    use HasFactory, SoftDeletes, HasContrats;

    protected $table = 'enseignants';

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Les affectations de l'enseignant : chaque affectation le rattache à un
     * couple (classe × matière). Un enseignant peut en avoir plusieurs.
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    /**
     * Les matières que l'enseignant est qualifié à enseigner.
     *
     * C'est sa COMPÉTENCE, indépendante des classes : à ne pas confondre avec
     * affectations(), qui décrit ce qu'il enseigne effectivement et où.
     */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'enseignant_matiere')
            ->withTimestamps();
    }
}
