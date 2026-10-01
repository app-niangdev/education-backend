<?php

namespace App\Repositories;

use App\Enums\StatutAnneeScolaire;
use App\Interfaces\AnneeScolaireRepositoryInterface;
use App\Models\AnneeScolaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AnneeScolaireRepository implements AnneeScolaireRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator
    {
        return AnneeScolaire::query()
            ->when($search, fn ($q) => $q->where('nom', 'ilike', "%{$search}%"))
            ->withCount('periodes')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return AnneeScolaire::orderBy('date_debut', 'desc')->get();
    }

    public function findById(int|string $id): AnneeScolaire
    {
        return AnneeScolaire::with('periodes')->findOrFail($id);
    }

    public function create(array $data): AnneeScolaire
    {
        return AnneeScolaire::create($data);
    }

    public function update(AnneeScolaire $annee, array $data): AnneeScolaire
    {
        $annee->update($data);

        return $annee->fresh('periodes');
    }

    /** Une annee porte-t-elle deja `en_cours` ? (soft-deletes exclus) */
    public function existeAnneeActive(): bool
    {
        return AnneeScolaire::where('en_cours', true)->exists();
    }

    /**
     * Marque l'annee comme active. `en_cours` etant hors de $fillable, la
     * bascule passe par forceFill : c'est le seul chemin d'ecriture prevu.
     */
    public function activer(AnneeScolaire $annee): AnneeScolaire
    {
        $annee->forceFill(['en_cours' => true])->save();

        return $annee->fresh('periodes');
    }

    public function desactiver(AnneeScolaire $annee): AnneeScolaire
    {
        $annee->forceFill(['en_cours' => false])->save();

        return $annee->fresh('periodes');
    }

    /**
     * Ferme toute autre annee active. A appeler AVANT d'ouvrir la nouvelle :
     * l'index unique partiel refuse deux lignes a `en_cours = true`, l'ordre
     * inverse echouerait.
     *
     * `statut` suit `en_cours` : une annee qui n'est plus active passe a
     * CLOTURER, sans quoi l'interface afficherait deux annees « En cours ».
     */
    public function desactiverAutresAnnees(?AnneeScolaire $sauf = null): void
    {
        AnneeScolaire::where('en_cours', true)
            ->when($sauf?->exists, fn ($q) => $q->where('id', '!=', $sauf->id))
            ->get()
            ->each(fn (AnneeScolaire $autre) => $autre->forceFill([
                'en_cours' => false,
                'statut'   => StatutAnneeScolaire::CLOTURER,
            ])->save());
    }

    public function delete(AnneeScolaire $annee): void
    {
        $annee->delete();
    }

    public function isUsed(AnneeScolaire $annee): bool
    {
        return $this->dependances($annee) !== [];
    }

    /**
     * Libelles des donnees rattachees a l'annee, pour nommer les bloqueurs
     * dans le message d'erreur.
     *
     * Toutes les tables listees ici pointent vers annee_scolaires ; quatre
     * d'entre elles (bulletins, depenses, frais scolaires, seances d'appel)
     * sont en cascadeOnDelete : sans ce controle, supprimer l'annee les
     * effacerait sans le moindre avertissement.
     */
    public function dependances(AnneeScolaire $annee): array
    {
        // [singulier, pluriel] : « 1 période » et non « 1 périodes ».
        $relations = [
            'periodes'       => ['période', 'périodes'],
            'classes'        => ['classe', 'classes'],
            'inscriptions'   => ['inscription', 'inscriptions'],
            'fraisScolaires' => ['grille de frais scolaires', 'grilles de frais scolaires'],
            'bulletins'      => ['bulletin', 'bulletins'],
            'depenses'       => ['dépense', 'dépenses'],
            'seancesAppel'   => ["séance d'appel", "séances d'appel"],
        ];

        $annee->loadCount(array_keys($relations));

        $bloqueurs = [];

        foreach ($relations as $relation => [$singulier, $pluriel]) {
            $nombre = (int) $annee->{$relation . '_count'};

            if ($nombre > 0) {
                $bloqueurs[] = $nombre . ' ' . ($nombre > 1 ? $pluriel : $singulier);
            }
        }

        return $bloqueurs;
    }
}
