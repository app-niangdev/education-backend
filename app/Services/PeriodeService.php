<?php

namespace App\Services;

use App\Interfaces\PeriodeRepositoryInterface;
use App\Interfaces\PeriodeServiceInterface;
use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PeriodeService implements PeriodeServiceInterface
{
    public function __construct(
        private readonly PeriodeRepositoryInterface $repository
    ) {}

    public function list(int $perPage, string $search, ?int $anneeId): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search, $anneeId);
    }

    public function allByAnnee(int $anneeId): Collection
    {
        return $this->repository->allByAnnee($anneeId);
    }

    public function find(int|string $id): Periode
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Periode
    {
        return $this->repository->create($data);
    }

    public function update(int|string $id, array $data): Periode
    {
        $periode = $this->repository->findById($id);

        return $this->repository->update($periode, $data);
    }

    public function delete(int|string $id): void
    {
        $periode = $this->repository->findById($id);

        if ($this->repository->isUsed($periode)) {
            abort(422, "Impossible de supprimer cette période : elle contient des notes ou bulletins associés.");
        }

        $this->repository->delete($periode);
    }
}
