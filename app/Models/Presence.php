<?php

namespace App\Models;

use App\Enums\StatutPresenceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Une anomalie relevée pour un élève sur une séance : absence, retard ou
 * renvoi. Un élève présent ne produit aucune ligne.
 *
 * Le justificatif éventuel est porté par medialibrary plutôt que par une
 * colonne, comme le logo de l'établissement.
 */
class Presence extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $table = 'presences';

    protected $fillable = [
        'seance_appel_id',
        'eleve_id',
        'statut',
        'minutes_retard',
        'justifie',
        'motif',
        'justifie_par',
        'justifie_le',
    ];

    protected $casts = [
        'statut'         => StatutPresenceEnum::class,
        'minutes_retard' => 'integer',
        'justifie'       => 'boolean',
        'justifie_le'    => 'datetime',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
        'media',
    ];

    protected $appends = [
        'statut_libelle',
        'justificatif_url',
    ];

    /**
     * Un seul justificatif : un document corrigé remplace le précédent plutôt
     * que de laisser deux pièces contradictoires sur la même absence.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('justificatif')
            ->singleFile()
            ->useDisk('justificatifs');
    }

    public function getStatutLibelleAttribute(): string
    {
        return $this->statut instanceof StatutPresenceEnum
            ? $this->statut->libelle()
            : (string) $this->statut;
    }

    public function getJustificatifUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('justificatif') ?: null;
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(SeanceAppel::class, 'seance_appel_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function justifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justifie_par');
    }
}
