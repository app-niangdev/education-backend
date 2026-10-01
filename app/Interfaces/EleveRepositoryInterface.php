<?php

namespace App\Interfaces;

use App\Models\Eleve;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EleveRepositoryInterface
{
    public function paginate(int $perPage, string $search, array $filters = []): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Eleve;

    public function create(array $data): Eleve;

    public function update(Eleve $eleve, array $data): Eleve;

    public function delete(Eleve $eleve): void;

    public function findTrashedById(int|string $id): Eleve;

    public function restore(Eleve $eleve): void;

    public function nextMatricule(): string;

    /**
     * Parmi les matricules soumis, ceux qui sont deja pris (suppressions
     * comprises), indexes par matricule.
     *
     * @param  array<int, string>  $matricules
     * @return array<string, true>
     */
    public function matriculesExistants(array $matricules): array;

    public function isUsed(Eleve $eleve): bool;
}
