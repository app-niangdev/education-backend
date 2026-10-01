<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\MatiereRepositoryInterface;
use App\Interfaces\MatiereServiceInterface;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MatiereService implements MatiereServiceInterface
{
    public function __construct(
        private readonly MatiereRepositoryInterface   $repository,
        private readonly ActivityLogServiceInterface  $activityLog,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Matiere
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Matiere
    {
        $matiere = $this->repository->create($data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'matieres',
            description: "Création de la matière « {$matiere->nom} »",
            subject:     $matiere,
            newValues:   $matiere->toArray(),
        );

        return $matiere;
    }

    public function update(int|string $id, array $data, User $authUser): Matiere
    {
        $matiere   = $this->repository->findById($id);
        $oldValues = $matiere->toArray();

        $matiere = $this->repository->update($matiere, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'matieres',
            description: "Modification de la matière « {$matiere->nom} »",
            subject:     $matiere,
            oldValues:   $oldValues,
            newValues:   $matiere->toArray(),
        );

        return $matiere;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $matiere = $this->repository->findById($id);

        if ($this->repository->isUsed($matiere)) {
            abort(422, "Impossible de supprimer cette matière : elle est enseignée dans une ou plusieurs classes.");
        }

        $this->repository->delete($matiere);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'matieres',
            description: "Suppression de la matière « {$matiere->nom} »",
            subject:     $matiere,
            oldValues:   $matiere->toArray(),
        );
    }
}
