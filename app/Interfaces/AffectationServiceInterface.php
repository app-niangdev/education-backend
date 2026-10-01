<?php

namespace App\Interfaces;

use App\Models\Affectation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AffectationServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function forEnseignant(int|string $enseignantId): Collection;

    public function forClasse(int|string $classeId): Collection;

    public function find(int|string $id): Affectation;

    public function create(array $data, User $authUser): Affectation;

    public function update(int|string $id, array $data, User $authUser): Affectation;

    public function delete(int|string $id, User $authUser): void;
}
