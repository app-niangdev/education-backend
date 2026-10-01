<?php

namespace App\Repositories;

use App\Interfaces\EnseignantRepositoryInterface;
use App\Models\Enseignant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EnseignantRepository implements EnseignantRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return Enseignant::query()
            ->with(['user.role', 'matieres', 'contratActif'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                      ->orWhere('last_name',  'ilike', "%{$search}%")
                      ->orWhere('phone_one',  'ilike', "%{$search}%")
                      // Nom complet dans les deux ordres.
                      ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", ["%{$search}%"])
                      ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", ["%{$search}%"]);
                })->orWhere('matricule', 'ilike', "%{$search}%")
                  // La spécialité n'est plus un texte : on cherche sur les
                  // matières liées (remplace l'ancien orWhere('specialite')).
                  ->orWhereHas('matieres', function ($q) use ($search) {
                      $q->where('nom',  'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                  });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Enseignant::with(['user', 'matieres', 'contratActif'])->get();
    }

    public function findById(int|string $id): Enseignant
    {
        return Enseignant::with(['user.role', 'matieres', 'contratActif'])->findOrFail($id);
    }

    public function create(array $data): Enseignant
    {
        $matieres = $this->extractMatieres($data);

        $enseignant = Enseignant::create($data);

        if ($matieres !== null) {
            $enseignant->matieres()->sync($matieres);
        }

        return $enseignant->load('matieres');
    }

    public function update(Enseignant $enseignant, array $data): Enseignant
    {
        $matieres = $this->extractMatieres($data);

        $enseignant->update($data);

        // sync() remplace la liste : envoyer [] retire toutes les spécialités,
        // alors qu'omettre la clé laisse l'existant intact.
        if ($matieres !== null) {
            $enseignant->matieres()->sync($matieres);
        }

        return $enseignant->fresh(['user.role', 'matieres']);
    }

    /**
     * Sort les ids de matières du tableau de données (par référence) : ce n'est
     * pas une colonne d'`enseignants` et cela ferait échouer un update Eloquent.
     * Retourne null si la clé est absente — « ne pas toucher aux spécialités ».
     */
    private function extractMatieres(array &$data): ?array
    {
        if (!array_key_exists('matieres', $data)) {
            return null;
        }

        $matieres = $data['matieres'];
        unset($data['matieres']);

        return array_values(array_unique(array_map('intval', (array) $matieres)));
    }

    public function delete(Enseignant $enseignant): void
    {
        $enseignant->delete();
    }

    public function findTrashedById(int|string $id): Enseignant
    {
        return Enseignant::onlyTrashed()->findOrFail($id);
    }

    public function restore(Enseignant $enseignant): void
    {
        $enseignant->restore();
    }

    public function nextMatricule(): string
    {
        $year   = now()->year;
        $prefix = "ENS-{$year}-";

        $last = Enseignant::withTrashed()
            ->where('matricule', 'like', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(matricule, LENGTH('{$prefix}') + 1) AS INTEGER) DESC")
            ->value('matricule');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
