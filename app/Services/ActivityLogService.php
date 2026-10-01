<?php

namespace App\Services;

use App\Interfaces\ActivityLogRepositoryInterface;
use App\Interfaces\ActivityLogServiceInterface;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService implements ActivityLogServiceInterface
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function list(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->activityLogRepository->paginate($perPage, $filters);
    }

    public function find(int|string $id): ActivityLog
    {
        return $this->activityLogRepository->findById($id);
    }

    public function log(
        User   $user,
        string $action,
        string $module,
        string $description,
        ?Model $subject   = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ActivityLog {
        return $this->activityLogRepository->create([
            'user_id'      => $user->id,
            'action'       => $action,
            'module'       => $module,
            'description'  => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => Request::ip(),
            'user_agent'   => Request::userAgent(),
        ]);
    }

    public function purge(int $days): int
    {
        return $this->activityLogRepository->deleteOlderThan($days);
    }
}
