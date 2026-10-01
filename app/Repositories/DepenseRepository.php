<?php

namespace App\Repositories;

use App\Interfaces\DepenseRepositoryInterface;
use App\Models\Depense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DepenseRepository implements DepenseRepositoryInterface
{
    private const RELATIONS = [
        'anneeScolaire',
        'utilisateur:id,first_name,last_name,email',
        // Qui a tranche : l'ecran l'affiche a cote du statut.
        'validateur:id,first_name,last_name,email',
    ];

    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->filtrer(Depense::query(), $filters)
            ->with(self::RELATIONS)
            // A date egale, la saisie la plus recente d'abord : l'ordre reste
            // stable d'une page a l'autre, ce que `latest()` seul ne garantit pas.
            ->orderByDesc('date_depense')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(array $filters = []): Collection
    {
        return $this->filtrer(Depense::query(), $filters)
            ->with(self::RELATIONS)
            ->orderByDesc('date_depense')
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int|string $id): Depense
    {
        return Depense::with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $data): Depense
    {
        return Depense::create($data);
    }

    public function update(Depense $depense, array $data): Depense
    {
        $depense->update($data);

        return $depense->fresh(self::RELATIONS);
    }

    public function delete(Depense $depense): void
    {
        $depense->delete();
    }

    /**
     * Les totaux ne retiennent que les depenses VALIDEES : une depense en
     * attente n'est pas une sortie de caisse engagee, et l'inclure gonflerait
     * le total de montants qu'un manager peut encore refuser.
     *
     * Le montant en attente est renvoye a part, pour que l'ecran puisse
     * l'afficher sans le melanger au reste.
     */
    public function totaux(array $filters): array
    {
        $parCategorie = $this->filtrer(Depense::query(), $filters)
            ->comptabilisees()
            ->select('categorie', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre'))
            ->groupBy('categorie')
            ->orderByDesc('total')
            ->get();

        $enAttente = $this->filtrer(Depense::query(), $filters)
            ->enAttente()
            ->selectRaw('COALESCE(SUM(montant), 0) as total, COUNT(*) as nombre')
            ->first();

        return [
            'total'  => (int) $parCategorie->sum('total'),
            'nombre' => (int) $parCategorie->sum('nombre'),
            // Ce qui attend une decision : affiche a part, jamais additionne.
            'total_en_attente'  => (int) ($enAttente->total ?? 0),
            'nombre_en_attente' => (int) ($enAttente->nombre ?? 0),
            'par_categorie' => $parCategorie->map(fn ($ligne) => [
                'categorie' => $ligne->categorie->value,
                'libelle'   => $ligne->categorie->libelle(),
                'total'     => (int) $ligne->total,
                'nombre'    => (int) $ligne->nombre,
            ])->values()->all(),
        ];
    }

    /**
     * Les filtres du module. Les trois filtres de date se combinent avec les
     * autres criteres, mais s'excluent entre eux : `date` (jour precis) prime
     * sur l'intervalle, qui prime sur le mois. Les appliquer simultanement
     * donnerait des resultats vides sans que l'utilisateur comprenne pourquoi.
     */
    private function filtrer(Builder $query, array $filters): Builder
    {
        $query
            ->when($filters['annee_scolaire_id'] ?? null, fn ($q, $v) => $q->where('annee_scolaire_id', $v))
            ->when($filters['categorie']         ?? null, fn ($q, $v) => $q->where('categorie', $v))
            ->when($filters['mode_paiement']     ?? null, fn ($q, $v) => $q->where('mode_paiement', $v))
            ->when($filters['statut']            ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('libelle',      'ilike', "%{$v}%")
                        ->orWhere('beneficiaire', 'ilike', "%{$v}%")
                        ->orWhere('reference',    'ilike', "%{$v}%")
                        ->orWhere('description',  'ilike', "%{$v}%");
                });
            });

        return $this->filtrerParDate($query, $filters);
    }

    /** Applique le premier filtre de date renseigne, par ordre de precision. */
    private function filtrerParDate(Builder $query, array $filters): Builder
    {
        $date = $filters['date'] ?? null;

        if ($date) {
            return $query->duJour($date);
        }

        $debut = $filters['date_from'] ?? null;
        $fin   = $filters['date_to']   ?? null;

        if ($debut || $fin) {
            return $query->entreDates($debut, $fin);
        }

        $mois  = $filters['mois']  ?? null;
        $annee = $filters['annee'] ?? null;

        if ($mois && $annee) {
            return $query->duMois((int) $mois, (int) $annee);
        }

        return $query;
    }
}
