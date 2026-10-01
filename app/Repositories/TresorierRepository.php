<?php

namespace App\Repositories;

use App\Interfaces\TresorierRepositoryInterface;
use App\Models\Tresorier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TresorierRepository implements TresorierRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Tresorier::query()
            ->with(['user.role', 'contratActif'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                      ->orWhere('last_name',  'ilike', "%{$search}%")
                      ->orWhere('phone_one',  'ilike', "%{$search}%")
                      // Nom complet dans les deux ordres.
                      ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"])
                      ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$search}%"]);
                })->orWhere('matricule', 'ilike', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Tresorier::with(['user', 'contratActif'])->get();
    }

    public function findById(int|string $id): Tresorier
    {
        return Tresorier::with(['user.role', 'contratActif'])->findOrFail($id);
    }

    public function create(array $data): Tresorier
    {
        return Tresorier::create($data);
    }

    public function update(Tresorier $tresorier, array $data): Tresorier
    {
        $tresorier->update($data);

        return $tresorier->fresh('user.role');
    }

    public function delete(Tresorier $tresorier): void
    {
        $tresorier->delete();
    }

    public function findTrashedById(int|string $id): Tresorier
    {
        return Tresorier::onlyTrashed()->findOrFail($id);
    }

    public function restore(Tresorier $tresorier): void
    {
        $tresorier->restore();
    }

    public function nextMatricule(): string
    {
        $year   = now()->year;
        $prefix = "TRE-{$year}-";

        $last = Tresorier::withTrashed()
            ->where('matricule', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(matricule, LENGTH('{$prefix}') + 1) AS INTEGER) DESC")
            ->value('matricule');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
