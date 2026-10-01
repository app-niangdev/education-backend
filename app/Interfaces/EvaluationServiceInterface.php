<?php

namespace App\Interfaces;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EvaluationServiceInterface
{
    /**
     * Les affectations sur lesquelles l'utilisateur peut créer des évaluations :
     * ses propres affectations s'il est enseignant, toutes sinon.
     */
    public function affectationsFor(User $authUser): Collection;

    /** Barème par défaut et types d'évaluation (pour préremplir le formulaire). */
    public function meta(): array;

    /**
     * Liste des évaluations. Pour un enseignant, la liste est automatiquement
     * bornée à ses propres affectations ; un manager/admin voit tout.
     */
    public function list(
        User $authUser,
        int $perPage,
        string $search,
        ?int $periodeId = null,
        ?int $affectationId = null,
    ): LengthAwarePaginator;

    public function find(User $authUser, int|string $id): Evaluation;

    /** L'évaluation + la grille des élèves de la classe avec leurs notes existantes. */
    public function gradeSheet(User $authUser, int|string $id): array;

    public function create(array $data, User $authUser): Evaluation;

    public function update(int|string $id, array $data, User $authUser): Evaluation;

    public function delete(int|string $id, User $authUser): void;

    /** Saisie/mise à jour en lot des notes d'une évaluation. */
    public function saveNotes(int|string $id, array $notes, User $authUser): array;
}
