<?php

namespace App\Interfaces;

use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PeriodeRepositoryInterface
{
    public function paginate(int $perPage, string $search, ?int $anneeId): LengthAwarePaginator;

    public function allByAnnee(int $anneeId): \Illuminate\Database\Eloquent\Collection;

    public function findById(int|string $id): Periode;

    public function create(array $data): Periode;

    public function update(Periode $periode, array $data): Periode;

    public function delete(Periode $periode): void;

    public function isUsed(Periode $periode): bool;
}
