<?php

namespace App\Interfaces;

use App\Models\Tresorier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TresorierRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Tresorier;

    public function create(array $data): Tresorier;

    public function update(Tresorier $tresorier, array $data): Tresorier;

    public function delete(Tresorier $tresorier): void;

    public function findTrashedById(int|string $id): Tresorier;

    public function restore(Tresorier $tresorier): void;

    public function nextMatricule(): string;
}
