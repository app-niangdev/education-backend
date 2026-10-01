<?php

namespace App\Repositories;

use App\Interfaces\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with('user:id,first_name,last_name,email')
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action']  ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['module']  ?? null, fn ($q, $v) => $q->where('module', 'ilike', "%{$v}%"))
            ->when($filters['search']  ?? null, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('description', 'ilike', "%{$v}%")
                        ->orWhere('module',      'ilike', "%{$v}%")
                        ->orWhere('action',      'ilike', "%{$v}%")
                        ->orWhereHas('user', fn ($u) =>
                            $u->where('first_name', 'ilike', "%{$v}%")
                              ->orWhere('last_name',  'ilike', "%{$v}%")
                              // Nom complet dans les deux ordres.
                              ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$v}%"])
                              ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$v}%"])
                        );
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): ActivityLog
    {
        return ActivityLog::create($data);
    }

    public function findById(int|string $id): ActivityLog
    {
        return ActivityLog::with('user:id,first_name,last_name,email')->findOrFail($id);
    }

    public function deleteOlderThan(int $days): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
