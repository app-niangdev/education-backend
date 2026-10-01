<?php

namespace App\Interfaces;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator;

    public function create(array $data): ActivityLog;

    public function findById(int|string $id): ActivityLog;

    public function deleteOlderThan(int $days): int;
}
