<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\AffectationRepositoryInterface;
use App\Interfaces\AffectationServiceInterface;
use App\Models\Affectation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AffectationService implements AffectationServiceInterface
{
    public function __construct(
        private readonly AffectationRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface    $activityLog,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function forEnseignant(int|string $enseignantId): Collection
    {
        return $this->repository->forEnseignant($enseignantId);
    }

    public function forClasse(int|string $classeId): Collection
    {
        return $this->repository->forClasse($classeId);
    }

    public function find(int|string $id): Affectation
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Affectation
    {
        // Un seul enseignant par couple (classe × matière).
        if ($this->repository->existsForClasseMatiere($data['classe_matiere_id'])) {
            abort(422, 'Un enseignant est déjà affecté à cette matière pour cette classe.');
        }

        $affectation   = $this->repository->create($data);
        $classeMatiere = $affectation->classeMatiere;

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'affectations',
            description: "Affectation de l'enseignant « {$affectation->enseignant?->user?->first_name} {$affectation->enseignant?->user?->last_name} » à « {$classeMatiere?->matiere?->nom} » en « {$classeMatiere?->classe?->nom} »",
            subject:     $affectation,
            newValues:   $affectation->toArray(),
        );

        return $affectation;
    }

    public function update(int|string $id, array $data, User $authUser): Affectation
    {
        $affectation = $this->repository->findById($id);
        $oldValues   = $affectation->toArray();

        $newClasseMatiereId = $data['classe_matiere_id'] ?? $affectation->classe_matiere_id;

        // Si on déplace l'affectation vers un autre couple, il doit être libre.
        if ((int) $newClasseMatiereId !== (int) $affectation->classe_matiere_id
            && $this->repository->existsForClasseMatiere($newClasseMatiereId)) {
            abort(422, 'Un enseignant est déjà affecté à cette matière pour cette classe.');
        }

        $affectation = $this->repository->update($affectation, [
            'enseignant_id'     => $data['enseignant_id'] ?? $affectation->enseignant_id,
            'classe_matiere_id' => $newClasseMatiereId,
        ]);

        $classeMatiere = $affectation->classeMatiere;

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'affectations',
            description: "Modification de l'affectation de « {$classeMatiere?->matiere?->nom} » en « {$classeMatiere?->classe?->nom} »",
            subject:     $affectation,
            oldValues:   $oldValues,
            newValues:   $affectation->toArray(),
        );

        return $affectation;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $affectation   = $this->repository->findById($id);
        $classeMatiere = $affectation->classeMatiere;

        $this->repository->delete($affectation);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'affectations',
            description: "Retrait de l'affectation de l'enseignant « {$affectation->enseignant?->user?->first_name} {$affectation->enseignant?->user?->last_name} » à « {$classeMatiere?->matiere?->nom} » en « {$classeMatiere?->classe?->nom} »",
            subject:     $affectation,
            oldValues:   $affectation->toArray(),
        );
    }
}
