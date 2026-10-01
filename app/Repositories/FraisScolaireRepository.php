<?php

namespace App\Repositories;

use App\Interfaces\FraisScolaireRepositoryInterface;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FraisScolaireRepository implements FraisScolaireRepositoryInterface
{
    private const RELATIONS = ['anneeScolaire', 'niveau'];

    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return FraisScolaire::query()
            ->with(self::RELATIONS)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('niveau', fn ($q) => $q
                        ->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%"))
                  ->orWhereHas('anneeScolaire', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return FraisScolaire::query()
            ->with(self::RELATIONS)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function findById(int|string $id): FraisScolaire
    {
        return FraisScolaire::with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $data): FraisScolaire
    {
        return FraisScolaire::create($data);
    }

    public function update(FraisScolaire $frais, array $data): FraisScolaire
    {
        $frais->update($data);

        return $frais->fresh(self::RELATIONS);
    }

    public function delete(FraisScolaire $frais): void
    {
        $frais->delete();
    }

    public function pourAnnee(int|string $anneeScolaireId): Collection
    {
        return FraisScolaire::query()
            ->with(self::RELATIONS)
            ->where('annee_scolaire_id', $anneeScolaireId)
            // Le tri suit la progression scolaire : cycle, puis rang du niveau.
            ->join('niveaux', 'niveaux.id', '=', 'frais_scolaires.niveau_id')
            ->orderBy('niveaux.cycle')
            ->orderBy('niveaux.ordre')
            ->select('frais_scolaires.*')
            ->get();
    }

    public function niveauxSansBareme(int|string $anneeScolaireId, int|string|null $niveauCourantId = null): Collection
    {
        return Niveau::query()
            ->where(function ($q) use ($anneeScolaireId, $niveauCourantId) {
                $q->whereDoesntHave(
                    'fraisScolaires',
                    fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId)
                );

                // En modification, le niveau deja porte par le bareme doit rester
                // selectionnable : sinon le select s'ouvrirait sur une valeur
                // absente de ses options et paraitrait vide.
                if ($niveauCourantId) {
                    $q->orWhere('id', $niveauCourantId);
                }
            })
            ->orderBy('cycle')
            ->orderBy('ordre')
            ->get();
    }

    public function existsPour(int|string $anneeScolaireId, int|string $niveauId, int|string|null $ignoreId = null): bool
    {
        return FraisScolaire::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('niveau_id', $niveauId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
