<?php

namespace App\Repositories;

use App\Interfaces\MatiereRepositoryInterface;
use App\Models\Matiere;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MatiereRepository implements MatiereRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Matiere::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->orderBy('nom')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Matiere::query()->orderBy('nom')->get();
    }

    public function findById(int|string $id): Matiere
    {
        return Matiere::findOrFail($id);
    }

    public function create(array $data): Matiere
    {
        return Matiere::create($data);
    }

    public function update(Matiere $matiere, array $data): Matiere
    {
        $matiere->update($data);

        return $matiere->fresh();
    }

    public function delete(Matiere $matiere): void
    {
        $matiere->delete();
    }

    public function isUsed(Matiere $matiere): bool
    {
        return $matiere->classeMatieres()->exists();
    }
}
