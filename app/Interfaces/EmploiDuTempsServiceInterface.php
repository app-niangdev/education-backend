<?php

namespace App\Interfaces;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface EmploiDuTempsServiceInterface
{
    public function forClasse(int|string $classeId): Collection;

    public function find(int|string $id): EmploiDuTemps;

    public function create(array $data, User $authUser): EmploiDuTemps;

    public function update(int|string $id, array $data, User $authUser): EmploiDuTemps;

    public function delete(int|string $id, User $authUser): void;

    /** Prepare les donnees (classe + creneaux groupes par jour) pour le PDF. */
    public function dataForPdf(int|string $classeId): array;

    public function classe(int|string $classeId): Classe;
}
