<?php

namespace App\Repositories;

use App\Enums\JourSemaineEnum;
use App\Interfaces\EmploiDuTempsRepositoryInterface;
use App\Models\EmploiDuTemps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EmploiDuTempsRepository implements EmploiDuTempsRepositoryInterface
{
    private const RELATIONS = [
        'affectation.enseignant.user',
        'affectation.classeMatiere.matiere',
    ];

    public function forClasse(int|string $classeId): Collection
    {
        return EmploiDuTemps::query()
            ->where('classe_id', $classeId)
            ->with(self::RELATIONS)
            ->orderBy('heure_debut')
            ->get()
            // Tri par ordre de jour (lundi -> samedi) applique en memoire :
            // l'enum porte l'ordre, l'ordre alphabetique du stockage ne convient pas.
            ->sortBy(fn (EmploiDuTemps $c) => [$c->jour->ordre(), $c->heure_debut])
            ->values();
    }

    public function findById(int|string $id): EmploiDuTemps
    {
        return EmploiDuTemps::with(self::RELATIONS)->findOrFail($id);
    }

    public function classeConflit(
        int|string $classeId,
        JourSemaineEnum $jour,
        string $heureDebut,
        string $heureFin,
        ?int $ignoreId = null
    ): bool {
        return EmploiDuTemps::query()
            ->where('classe_id', $classeId)
            ->where('jour', $jour->value)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where(fn (Builder $q) => $this->chevauche($q, $heureDebut, $heureFin))
            ->exists();
    }

    public function enseignantConflit(
        int|string $enseignantId,
        JourSemaineEnum $jour,
        string $heureDebut,
        string $heureFin,
        ?int $ignoreId = null
    ): bool {
        return EmploiDuTemps::query()
            ->where('jour', $jour->value)
            ->whereHas('affectation', fn (Builder $q) => $q->where('enseignant_id', $enseignantId))
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where(fn (Builder $q) => $this->chevauche($q, $heureDebut, $heureFin))
            ->exists();
    }

    /**
     * Deux intervalles se chevauchent si debut < fin_existante ET fin > debut_existant.
     * Les creneaux qui se touchent (fin = debut suivant) ne sont pas en conflit.
     */
    private function chevauche(Builder $q, string $heureDebut, string $heureFin): Builder
    {
        return $q->where('heure_debut', '<', $heureFin)
                 ->where('heure_fin', '>', $heureDebut);
    }

    public function create(array $data): EmploiDuTemps
    {
        $creneau = EmploiDuTemps::create($data);

        return $creneau->fresh(self::RELATIONS);
    }

    public function update(EmploiDuTemps $creneau, array $data): EmploiDuTemps
    {
        $creneau->update($data);

        return $creneau->fresh(self::RELATIONS);
    }

    public function delete(EmploiDuTemps $creneau): void
    {
        $creneau->delete();
    }
}
