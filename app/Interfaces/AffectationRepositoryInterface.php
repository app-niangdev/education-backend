<?php

namespace App\Interfaces;

use App\Models\Affectation;
use App\Models\ClasseMatiere;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AffectationRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    /** Les affectations d'un enseignant (avec classe et matière). */
    public function forEnseignant(int|string $enseignantId): Collection;

    /** Le programme d'une classe : ses couples (matière + coefficient) et l'enseignant affecté. */
    public function forClasse(int|string $classeId): Collection;

    public function findById(int|string $id): Affectation;

    public function findClasseMatiere(int|string $classeMatiereId): ClasseMatiere;

    public function existsForClasseMatiere(int|string $classeMatiereId): bool;

    public function create(array $data): Affectation;

    public function update(Affectation $affectation, array $data): Affectation;

    public function delete(Affectation $affectation): void;
}
