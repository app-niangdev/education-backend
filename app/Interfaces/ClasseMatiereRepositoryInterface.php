<?php

namespace App\Interfaces;

use App\Models\ClasseMatiere;
use Illuminate\Database\Eloquent\Collection;

interface ClasseMatiereRepositoryInterface
{
    /** Le programme d'une classe : matières + coefficients + enseignant affecté. */
    public function forClasse(int|string $classeId): Collection;

    public function findById(int|string $id): ClasseMatiere;

    public function existsForClasseAndMatiere(int|string $classeId, int|string $matiereId): bool;

    public function create(array $data): ClasseMatiere;

    public function update(ClasseMatiere $classeMatiere, array $data): ClasseMatiere;

    public function delete(ClasseMatiere $classeMatiere): void;

    public function isUsed(ClasseMatiere $classeMatiere): bool;

    /** La position suivante disponible : une matière ajoutée va en fin de programme. */
    public function prochainOrdre(int|string $classeId): int;

    /**
     * Applique un nouvel ordre d'affichage au programme.
     * `$ordres` : [classe_matiere_id => position].
     */
    public function reordonner(array $ordres): void;
}
