<?php

namespace App\Models;

use App\Enums\TypePeriodeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class Periode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'libelle',
        'type',
        'ordre',
        'date_debut',
        'date_fin',
        'date_fin_saisie_notes',
        'annee_scolaire_id',
    ];

    protected $casts = [
        'type'                     => TypePeriodeEnum::class,
        'date_debut'               => 'date',
        'date_fin'                 => 'date',
        'date_fin_saisie_notes'    => 'date',
    ];

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    /**
     * La saisie des notes est close lorsque la date limite est dépassée.
     * Sans date limite définie, la saisie reste ouverte.
     */
    public function saisieNotesFermee(): bool
    {
        return $this->date_fin_saisie_notes !== null
            && Carbon::now()->startOfDay()->greaterThan($this->date_fin_saisie_notes);
    }
}
