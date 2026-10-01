<?php

namespace App\Interfaces;

use App\Models\Niveau;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NiveauRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): \Illuminate\Database\Eloquent\Collection;

    /**
     * Les niveaux dans l'ordre de progression (cycle puis rang), chacun portant
     * son barème de l'année donnée s'il en a un.
     */
    public function avecBaremeAnnee(int|string $anneeScolaireId): \Illuminate\Database\Eloquent\Collection;

    public function findById(int|string $id): Niveau;

    public function create(array $data): Niveau;

    public function update(Niveau $niveau, array $data): Niveau;

    public function delete(Niveau $niveau): void;

    public function isUsed(Niveau $niveau): bool;
}
