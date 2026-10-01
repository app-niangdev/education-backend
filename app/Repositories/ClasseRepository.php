<?php

namespace App\Repositories;

use App\Interfaces\ClasseRepositoryInterface;
use App\Models\Classe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClasseRepository implements ClasseRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Classe::query()
            ->with(['niveau', 'anneeScolaire'])
            ->withCount('inscriptions')
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nom',   'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhereHas('niveau', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Classe::query()
            ->with(['niveau', 'anneeScolaire'])
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->orderBy('nom', 'asc')
            ->get();
    }

    public function findById(int|string $id): Classe
    {
        return Classe::with(['niveau', 'anneeScolaire'])->findOrFail($id);
    }

    public function create(array $data): Classe
    {
        return Classe::create($data);
    }

    public function update(Classe $classe, array $data): Classe
    {
        $classe->update($data);

        return $classe->fresh(['niveau', 'anneeScolaire']);
    }

    public function delete(Classe $classe): void
    {
        $classe->delete();
    }

    public function isUsed(Classe $classe): bool
    {
        return $classe->inscriptions()->exists() || $classe->classeMatieres()->exists();
    }
}
