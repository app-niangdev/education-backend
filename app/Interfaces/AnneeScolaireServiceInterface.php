<?php

namespace App\Interfaces;

use App\Models\AnneeScolaire;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AnneeScolaireServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): AnneeScolaire;

    public function create(array $data, User $authUser): AnneeScolaire;

    public function update(int|string $id, array $data, User $authUser): AnneeScolaire;

    public function delete(int|string $id, User $authUser): void;
}
