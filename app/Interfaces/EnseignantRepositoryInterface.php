<?php

namespace App\Interfaces;

use App\Models\Enseignant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EnseignantRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Enseignant;

    public function create(array $data): Enseignant;

    public function update(Enseignant $enseignant, array $data): Enseignant;

    public function delete(Enseignant $enseignant): void;

    public function findTrashedById(int|string $id): Enseignant;

    public function restore(Enseignant $enseignant): void;

    public function nextMatricule(): string;
}
