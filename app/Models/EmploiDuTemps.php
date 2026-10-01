<?php

namespace App\Models;

use App\Enums\JourSemaineEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un creneau de cours dans l'emploi du temps d'une classe.
 */
class EmploiDuTemps extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'classe_id',
        'affectation_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'salle',
    ];

    protected $casts = [
        'jour' => JourSemaineEnum::class,
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

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    /** Les seances tenues sur ce creneau, une par date d'appel. */
    public function seances(): HasMany
    {
        return $this->hasMany(SeanceAppel::class, 'emploi_du_temps_id');
    }
}
