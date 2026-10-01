<?php

namespace App\Interfaces;

use App\Models\Affectation;
use App\Models\Evaluation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EvaluationRepositoryInterface
{
    /**
     * Liste paginée des évaluations, éventuellement bornée à un enseignant
     * (pour la vue prof) et filtrée par période / affectation.
     */
    public function paginate(
        int $perPage,
        string $search,
        ?int $enseignantId = null,
        ?int $periodeId = null,
        ?int $affectationId = null,
    ): LengthAwarePaginator;

    public function findById(int|string $id): Evaluation;

    /** L'évaluation avec ses notes et les élèves de la classe (pour la grille de saisie). */
    public function findWithNotes(int|string $id): Evaluation;

    public function findAffectation(int|string $affectationId): Affectation;

    /** Les affectations, éventuellement bornées à un enseignant (vue prof). */
    public function affectations(?int $enseignantId = null): Collection;

    /**
     * Une évaluation du même type existe-t-elle déjà pour ce couple
     * (affectation × période) ? `$exceptId` exclut l'évaluation en cours d'édition.
     */
    public function existsForType(
        int|string $affectationId,
        int|string $periodeId,
        string $type,
        int|string|null $exceptId = null,
    ): bool;

    public function create(array $data): Evaluation;

    public function update(Evaluation $evaluation, array $data): Evaluation;

    public function delete(Evaluation $evaluation): void;

    /** Les élèves inscrits (inscription validée, année en cours) dans la classe de l'affectation. */
    public function elevesForAffectation(Affectation $affectation): Collection;

    /**
     * Upsert d'un lot de notes pour une évaluation. Chaque entrée :
     * ['eleve_id' => int, 'valeur' => ?float, 'absent' => bool, 'appreciation' => ?string].
     */
    public function upsertNotes(Evaluation $evaluation, array $notes): void;
}
