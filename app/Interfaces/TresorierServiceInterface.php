<?php

namespace App\Interfaces;

use App\Models\Tresorier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TresorierServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Tresorier;

    public function create(array $data, User $authUser): Tresorier;

    public function update(int|string $id, array $data, User $authUser): Tresorier;

    public function delete(int|string $id, User $authUser): void;

    public function restore(int|string $id, User $authUser): Tresorier;
}
