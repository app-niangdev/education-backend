<?php

namespace App\Repositories;

use App\Interfaces\AffectationRepositoryInterface;
use App\Models\Affectation;
use App\Models\ClasseMatiere;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AffectationRepository implements AffectationRepositoryInterface
{
    private const RELATIONS = [
        'enseignant.user',
        'classeMatiere.matiere',
        'classeMatiere.classe',
    ];

    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Affectation::query()
            ->with(self::RELATIONS)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('enseignant.user', fn ($q) => $q
                    ->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    // Nom complet dans les deux ordres.
                    ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"])
                    ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$search}%"]))
                  ->orWhereHas('classeMatiere.matiere', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"))
                  ->orWhereHas('classeMatiere.classe', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function forEnseignant(int|string $enseignantId): Collection
    {
        return Affectation::query()
            ->where('enseignant_id', $enseignantId)
            ->with(['classeMatiere.matiere', 'classeMatiere.classe'])
            ->get();
    }

    public function forClasse(int|string $classeId): Collection
    {
        return Affectation::query()
            ->whereHas('classeMatiere', fn ($q) => $q->where('classe_id', $classeId))
            ->with(self::RELATIONS)
            ->get();
    }

    public function findById(int|string $id): Affectation
    {
        return Affectation::with(self::RELATIONS)->findOrFail($id);
    }

    public function findClasseMatiere(int|string $classeMatiereId): ClasseMatiere
    {
        return ClasseMatiere::with(['matiere', 'classe'])->findOrFail($classeMatiereId);
    }

    public function existsForClasseMatiere(int|string $classeMatiereId): bool
    {
        return Affectation::where('classe_matiere_id', $classeMatiereId)->exists();
    }

    public function create(array $data): Affectation
    {
        $affectation = Affectation::create($data);

        return $affectation->fresh(self::RELATIONS);
    }

    public function update(Affectation $affectation, array $data): Affectation
    {
        $affectation->update($data);

        return $affectation->fresh(self::RELATIONS);
    }

    public function delete(Affectation $affectation): void
    {
        $affectation->delete();
    }
}
