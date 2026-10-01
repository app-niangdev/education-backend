<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage, string $search, string $searchRole): LengthAwarePaginator
    {
        return User::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                      ->orWhere('last_name',  'ilike', "%{$search}%")
                      ->orWhere('phone_one',  'ilike', "%{$search}%")
                      ->orWhere('email',      'ilike', "%{$search}%")
                      // Nom complet dans les deux ordres.
                      ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"])
                      ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$search}%"]);
                });
            })
            ->when($searchRole, function ($query) use ($searchRole) {
                $query->whereHas('role', fn ($q) => $q->where('name', 'ilike', "%{$searchRole}%"));
            })
            ->with('role')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int|string $id): User
    {
        return User::with('role')->findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('role');
    }

    public function findTrashedById(int|string $id, ?int $tenantId = null): User
    {
        return User::onlyTrashed()->findOrFail($id);
    }

    public function restore(User $user): void
    {
        $user->restore();
    }

    public function forceDelete(int|string $id): void
    {
        User::withTrashed()->findOrFail($id)->forceDelete();
    }
}
