<?php

namespace App\Interfaces;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EnseignantServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Enseignant;

    public function create(array $data, User $authUser): Enseignant;

    public function update(int|string $id, array $data, User $authUser): Enseignant;

    public function delete(int|string $id, User $authUser): void;

    public function restore(int|string $id, User $authUser): Enseignant;
}
