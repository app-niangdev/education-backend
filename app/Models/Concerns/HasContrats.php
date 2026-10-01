<?php

namespace App\Models\Concerns;

use App\Models\Contrat;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Rattache un profil du personnel a ses contrats.
 *
 * Les conditions d'engagement ne vivent plus sur le profil mais sur le contrat.
 * Pour que les ecrans qui affichaient `type_contrat` ou la remuneration
 * continuent de fonctionner, le profil les re-expose en lecture depuis son
 * contrat en cours — sans jamais les stocker, ce qui rendrait la desynchro
 * possible.
 */
trait HasContrats
{
    /** Tout l'historique contractuel, du plus recent au plus ancien. */
    public function contrats(): MorphMany
    {
        return $this->morphMany(Contrat::class, 'contractable')
                    ->orderByDesc('date_debut')
                    ->orderByDesc('id');
    }

    /**
     * Le contrat qui lie actuellement l'employe.
     *
     * En cas d'anomalie (deux contrats actifs), le plus recent l'emporte : c'est
     * la lecture la moins surprenante, un avenant recent primant sur l'ancien.
     */
    public function contratActif(): MorphOne
    {
        return $this->morphOne(Contrat::class, 'contractable')
                    ->enCours()
                    ->latest('date_debut')
                    ->latest('id');
    }

    /**
     * Les champs que les listes et fiches lisaient sur le profil, servis depuis
     * le contrat en cours. `null` quand l'employe n'a aucun contrat ouvert.
     */
    public function getTypeContratAttribute(): ?string
    {
        return $this->contratActif?->type_contrat?->value;
    }

    public function getDateEmbaucheAttribute(): ?string
    {
        return $this->contratActif?->date_debut?->toDateString();
    }

    public function getSalaireBaseAttribute(): ?int
    {
        return $this->contratActif?->salaire_base;
    }

    public function getModeRemunerationAttribute(): ?string
    {
        return $this->contratActif?->mode_remuneration?->value;
    }

    public function getRemunerationLibelleAttribute(): ?string
    {
        return $this->contratActif?->remuneration_libelle;
    }
}
