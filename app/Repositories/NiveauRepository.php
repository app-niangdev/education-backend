<?php

namespace App\Repositories;

use App\Interfaces\NiveauRepositoryInterface;
use App\Models\Niveau;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class NiveauRepository implements NiveauRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Niveau::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nom',  'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('cycle', 'ilike', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Niveau::orderBy('id', 'asc')->get();
    }

    /**
     * Le tri suit la progression scolaire (cycle, puis rang dans le cycle)
     * plutot que l'ordre de creation : c'est ainsi que la grille tarifaire se
     * lit. Le bareme est filtre sur l'annee au chargement, la relation
     * `fraisScolaire` etant un HasOne sur une table ou (annee, niveau) est
     * unique : elle ramene donc une ligne ou null.
     */
    public function avecBaremeAnnee(int|string $anneeScolaireId): Collection
    {
        return Niveau::query()
            ->with([
                'fraisScolaire' => fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId),
            ])
            ->orderBy('cycle')
            ->orderBy('ordre')
            ->get();
    }

    public function findById(int|string $id): Niveau
    {
        return Niveau::findOrFail($id);
    }

    public function create(array $data): Niveau
    {
        return Niveau::create($data);
    }

    public function update(Niveau $niveau, array $data): Niveau
    {
        $niveau->update($data);

        return $niveau->fresh();
    }

    public function delete(Niveau $niveau): void
    {
        $niveau->delete();
    }

    public function isUsed(Niveau $niveau): bool
    {
        // Seules les classes retiennent la suppression : elles portent des
        // eleves inscrits, qu'un niveau efface laisserait sans rattachement.
        //
        // Le bareme, lui, ne retient rien : niveau et tarif se creent d'un seul
        // geste dans l'interface unifiee, ils se suppriment de meme (le service
        // efface les baremes avant le niveau, en soft delete pour preserver
        // l'historique de facturation).
        return $niveau->classes()->exists();
    }
}
