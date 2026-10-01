<?php

namespace App\Models;

use App\Enums\AppreciationEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une matière sur le bulletin d'un élève.
 *
 * `notee` distingue une matière évaluée d'une matière inscrite au programme
 * mais sans aucune note : cette dernière s'imprime en tirets et ne compte pas
 * dans le total des coefficients.
 *
 * Les moyennes portent trois décimales (voir la migration) ; l'affichage à
 * deux décimales est du ressort du gabarit PDF, pas du stockage.
 */
class BulletinLigne extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bulletin_lignes';

    protected $fillable = [
        'bulletin_id',
        'matiere_id',
        'matiere_nom',
        'ordre',
        'moy_devoirs',
        'composition',
        'moyenne',
        'coefficient',
        'moy_x_coef',
        'rang',
        'rang_ex_aequo',
        'appreciation',
        'notee',
    ];

    protected $casts = [
        'appreciation'  => AppreciationEnum::class,
        'ordre'         => 'integer',
        'moy_devoirs'   => 'decimal:3',
        'composition'   => 'decimal:3',
        'moyenne'       => 'decimal:3',
        'coefficient'   => 'integer',
        'moy_x_coef'    => 'decimal:3',
        'rang'          => 'integer',
        'rang_ex_aequo' => 'boolean',
        'notee'         => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'appreciation_libelle',
    ];

    public function getAppreciationLibelleAttribute(): ?string
    {
        return $this->appreciation?->libelle();
    }

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
}
