<?php

namespace App\Repositories;

use App\Interfaces\PeriodeRepositoryInterface;
use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PeriodeRepository implements PeriodeRepositoryInterface
{
    public function paginate(int $perPage, string $search, ?int $anneeId): LengthAwarePaginator
    {
        return Periode::query()
            ->with('anneeScolaire')
            ->when($anneeId, fn ($q) => $q->where('annee_scolaire_id', $anneeId))
            ->when($search, fn ($q) => $q->where('libelle', 'ilike', "%{$search}%"))
            ->orderBy('ordre')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allByAnnee(int $anneeId): Collection
    {
        return Periode::where('annee_scolaire_id', $anneeId)
            ->orderBy('ordre')
            ->get();
    }

    public function findById(int|string $id): Periode
    {
        return Periode::with('anneeScolaire')->findOrFail($id);
    }

    public function create(array $data): Periode
    {
        return Periode::create($data);
    }

    public function update(Periode $periode, array $data): Periode
    {
        $periode->update($data);

        return $periode->fresh('anneeScolaire');
    }

    public function delete(Periode $periode): void
    {
        $periode->delete();
    }

    public function isUsed(Periode $periode): bool
    {
        // À compléter quand les tables notes/bulletins existeront
        return false;
    }
}
