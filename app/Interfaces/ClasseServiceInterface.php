<?php

namespace App\Interfaces;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClasseServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Classe;

    public function create(array $data, User $authUser): Classe;

    public function update(int|string $id, array $data, User $authUser): Classe;

    public function delete(int|string $id, User $authUser): void;
}
