<?php

namespace App\Interfaces;

use App\Enums\JourSemaineEnum;
use App\Models\EmploiDuTemps;
use Illuminate\Database\Eloquent\Collection;

interface EmploiDuTempsRepositoryInterface
{
    /** Les creneaux d'une classe, tries par jour puis heure. */
    public function forClasse(int|string $classeId): Collection;

    public function findById(int|string $id): EmploiDuTemps;

    /**
     * Un creneau chevauche-t-il un cours existant pour la meme CLASSE
     * sur le meme jour ? (hors creneau $ignoreId en cas d'edition)
     */
    public function classeConflit(
        int|string $classeId,
        JourSemaineEnum $jour,
        string $heureDebut,
        string $heureFin,
        ?int $ignoreId = null
    ): bool;

    /**
     * Un creneau chevauche-t-il un cours existant pour le meme ENSEIGNANT
     * (a travers toutes ses classes) sur le meme jour ?
     */
    public function enseignantConflit(
        int|string $enseignantId,
        JourSemaineEnum $jour,
        string $heureDebut,
        string $heureFin,
        ?int $ignoreId = null
    ): bool;

    public function create(array $data): EmploiDuTemps;

    public function update(EmploiDuTemps $creneau, array $data): EmploiDuTemps;

    public function delete(EmploiDuTemps $creneau): void;
}
