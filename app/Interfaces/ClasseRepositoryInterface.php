<?php

namespace App\Interfaces;

use App\Models\Classe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClasseRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Classe;

    public function create(array $data): Classe;

    public function update(Classe $classe, array $data): Classe;

    public function delete(Classe $classe): void;

    public function isUsed(Classe $classe): bool;
}
