<?php

namespace App\Interfaces;

use App\Models\Matiere;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MatiereRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Matiere;

    public function create(array $data): Matiere;

    public function update(Matiere $matiere, array $data): Matiere;

    public function delete(Matiere $matiere): void;

    public function isUsed(Matiere $matiere): bool;
}
