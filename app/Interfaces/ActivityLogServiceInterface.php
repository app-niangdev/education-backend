<?php

namespace App\Interfaces;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface ActivityLogServiceInterface
{
    public function list(int $perPage, array $filters): LengthAwarePaginator;

    public function find(int|string $id): ActivityLog;

    public function log(
        User   $user,
        string $action,
        string $module,
        string $description,
        ?Model $subject    = null,
        ?array $oldValues  = null,
        ?array $newValues  = null,
    ): ActivityLog;

    public function purge(int $days): int;
}
