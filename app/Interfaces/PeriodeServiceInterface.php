<?php

namespace App\Interfaces;

use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PeriodeServiceInterface
{
    public function list(int $perPage, string $search, ?int $anneeId): LengthAwarePaginator;

    public function allByAnnee(int $anneeId): \Illuminate\Database\Eloquent\Collection;

    public function find(int|string $id): Periode;

    public function create(array $data): Periode;

    public function update(int|string $id, array $data): Periode;

    public function delete(int|string $id): void;
}
