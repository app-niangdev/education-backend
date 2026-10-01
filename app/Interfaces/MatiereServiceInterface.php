<?php

namespace App\Interfaces;

use App\Models\Matiere;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MatiereServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Matiere;

    public function create(array $data, User $authUser): Matiere;

    public function update(int|string $id, array $data, User $authUser): Matiere;

    public function delete(int|string $id, User $authUser): void;
}
