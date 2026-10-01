<?php

namespace App\Interfaces;

use App\Models\Surveillant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SurveillantRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Surveillant;

    public function create(array $data): Surveillant;

    public function update(Surveillant $surveillant, array $data): Surveillant;

    public function delete(Surveillant $surveillant): void;

    public function findTrashedById(int|string $id): Surveillant;

    public function restore(Surveillant $surveillant): void;

    public function nextMatricule(): string;
}
