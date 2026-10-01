<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\ClasseRepositoryInterface;
use App\Interfaces\ClasseServiceInterface;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClasseService implements ClasseServiceInterface
{
    public function __construct(
        private readonly ClasseRepositoryInterface   $repository,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Classe
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Classe
    {
        $classe = $this->repository->create($data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'classes',
            description: "Création de la classe « {$classe->nom} »",
            subject:     $classe,
            newValues:   $classe->toArray(),
        );

        return $classe;
    }

    public function update(int|string $id, array $data, User $authUser): Classe
    {
        $classe    = $this->repository->findById($id);
        $oldValues = $classe->toArray();

        $classe = $this->repository->update($classe, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'classes',
            description: "Modification de la classe « {$classe->nom} »",
            subject:     $classe,
            oldValues:   $oldValues,
            newValues:   $classe->toArray(),
        );

        return $classe;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $classe = $this->repository->findById($id);

        if ($this->repository->isUsed($classe)) {
            abort(422, "Impossible de supprimer cette classe : elle est utilisée dans des inscriptions ou des cours.");
        }

        $this->repository->delete($classe);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'classes',
            description: "Suppression de la classe « {$classe->nom} »",
            subject:     $classe,
            oldValues:   $classe->toArray(),
        );
    }
}
