<?php

namespace App\Repositories;

use App\Interfaces\ClasseMatiereRepositoryInterface;
use App\Models\ClasseMatiere;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClasseMatiereRepository implements ClasseMatiereRepositoryInterface
{
    public function forClasse(int|string $classeId): Collection
    {
        return ClasseMatiere::query()
            ->where('classe_id', $classeId)
            ->with([
                'matiere',
                'affectation.enseignant.user',
            ])
            ->join('matieres', 'matieres.id', '=', 'classe_matiere.matiere_id')
            // L'ordre du programme prime : c'est celui qu'imprime le bulletin.
            // Le nom départage les matières pas encore classées (ordre 0).
            ->orderBy('classe_matiere.ordre')
            ->orderBy('matieres.nom')
            ->select('classe_matiere.*')
            ->get();
    }

    public function prochainOrdre(int|string $classeId): int
    {
        return (int) ClasseMatiere::where('classe_id', $classeId)->max('ordre') + 1;
    }

    /**
     * Applique un nouvel ordre à un lot de matières.
     * `$ordres` : [classe_matiere_id => position].
     */
    public function reordonner(array $ordres): void
    {
        DB::transaction(function () use ($ordres) {
            foreach ($ordres as $id => $position) {
                ClasseMatiere::where('id', $id)->update(['ordre' => $position]);
            }
        });
    }

    public function findById(int|string $id): ClasseMatiere
    {
        return ClasseMatiere::with(['classe', 'matiere', 'affectation.enseignant.user'])
            ->findOrFail($id);
    }

    public function existsForClasseAndMatiere(int|string $classeId, int|string $matiereId): bool
    {
        return ClasseMatiere::where('classe_id', $classeId)
            ->where('matiere_id', $matiereId)
            ->exists();
    }

    public function create(array $data): ClasseMatiere
    {
        return ClasseMatiere::create($data);
    }

    public function update(ClasseMatiere $classeMatiere, array $data): ClasseMatiere
    {
        $classeMatiere->update($data);

        return $classeMatiere->fresh(['classe', 'matiere', 'affectation.enseignant.user']);
    }

    public function delete(ClasseMatiere $classeMatiere): void
    {
        $classeMatiere->delete();
    }

    public function isUsed(ClasseMatiere $classeMatiere): bool
    {
        return $classeMatiere->affectation()->exists();
    }
}
