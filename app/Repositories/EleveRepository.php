<?php

namespace App\Repositories;

use App\Interfaces\EleveRepositoryInterface;
use App\Models\Eleve;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EleveRepository implements EleveRepositoryInterface
{
    private const RELATIONS = ['tuteur', 'classeActuelle.niveau'];

    public function paginate(int $perPage, string $search, array $filters = []): LengthAwarePaginator
    {
        return Eleve::query()
            ->with(self::RELATIONS)
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $motif = "%{$search}%";

                $q->where('nom', 'ilike', $motif)
                  ->orWhere('prenom', 'ilike', $motif)
                  ->orWhere('matricule', 'ilike', $motif)
                  ->orWhere('telephone', 'ilike', $motif)
                  // Nom complet dans les deux ordres : une saisie « Awa Diop »
                  // comme « Diop Awa » doit trouver le meme eleve, l'usager ne
                  // sachant pas toujours si on affiche prenom-nom ou l'inverse.
                  ->orWhereRaw("(prenom || ' ' || nom) ilike ?", [$motif])
                  ->orWhereRaw("(nom || ' ' || prenom) ilike ?", [$motif])
                  ->orWhereHas('tuteur', fn ($q) => $q
                      ->where('nom', 'ilike', $motif)
                      ->orWhere('prenom', 'ilike', $motif)
                      ->orWhere('telephone_principal', 'ilike', $motif)
                      ->orWhere('nin', 'ilike', $motif));
            }))
            // Recherche ciblee sur le seul matricule, en complement de la
            // recherche large ci-dessus. Un matricule est un identifiant
            // exact : le chercher parmi les noms et telephones ramene du
            // bruit, alors que l'agent qui le saisit sait deja qui il cherche.
            ->when($filters['matricule'] ?? null, fn ($q, $m) => $q->where('matricule', 'ilike', "%{$m}%"))
            ->when($filters['classe_actuelle_id'] ?? null, fn ($q, $id) => $q->where('classe_actuelle_id', $id))
            ->when($filters['statut_inscription'] ?? null, fn ($q, $s) => $q->where('statut_inscription', $s))
            ->when($filters['sexe'] ?? null, fn ($q, $sexe) => $q->where('sexe', $sexe))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Eleve::query()
            ->with(self::RELATIONS)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function findById(int|string $id): Eleve
    {
        return Eleve::with([...self::RELATIONS, 'inscriptions.classe', 'inscriptions.anneeScolaire'])
            ->findOrFail($id);
    }

    public function create(array $data): Eleve
    {
        return Eleve::create($data);
    }

    public function update(Eleve $eleve, array $data): Eleve
    {
        $eleve->update($data);

        return $eleve->fresh(self::RELATIONS);
    }

    public function delete(Eleve $eleve): void
    {
        $eleve->delete();
    }

    public function findTrashedById(int|string $id): Eleve
    {
        return Eleve::onlyTrashed()->findOrFail($id);
    }

    public function restore(Eleve $eleve): void
    {
        $eleve->restore();
    }

    /**
     * Format ELV-2026-001. Les eleves supprimes comptent : un matricule deja
     * emis ne doit jamais etre reattribue.
     */
    public function nextMatricule(): string
    {
        $prefix = 'ELV-' . now()->year . '-';

        $last = Eleve::withTrashed()
            ->where('matricule', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(matricule, LENGTH('{$prefix}') + 1) AS INTEGER) DESC")
            ->value('matricule');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Parmi les matricules soumis, ceux qui sont deja pris.
     *
     * Renvoie un tableau indexe par matricule, pour que l'appelant teste par
     * isset() plutot que par in_array() — sur un fichier de plusieurs
     * centaines de lignes, la difference se voit.
     *
     * Les eleves supprimes sont inclus : leur matricule reste reserve, comme
     * dans nextMatricule().
     *
     * @param  array<int, string>  $matricules
     * @return array<string, true>
     */
    public function matriculesExistants(array $matricules): array
    {
        if ($matricules === []) {
            return [];
        }

        return Eleve::withTrashed()
            ->whereIn('matricule', $matricules)
            ->pluck('matricule')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    public function isUsed(Eleve $eleve): bool
    {
        return $eleve->inscriptions()->exists();
    }
}
