<?php

namespace App\Repositories;

use App\Enums\StatutAnneeScolaire;
use App\Enums\StatutInscriptionEnum;
use App\Interfaces\InscriptionRepositoryInterface;
use App\Models\AnneeScolaire;
use App\Models\Inscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InscriptionRepository implements InscriptionRepositoryInterface
{
    private const RELATIONS = ['eleve', 'classe.niveau', 'anneeScolaire', 'paiements'];

    public function paginate(int $perPage, string $search, array $filters = []): LengthAwarePaginator
    {
        return Inscription::query()
            ->with(self::RELATIONS)
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('numero_inscription', 'ilike', "%{$search}%")
                  ->orWhereHas('eleve', fn ($q) => $q
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhere('matricule', 'ilike', "%{$search}%")
                      // Nom complet dans les deux ordres.
                      ->orWhereRaw("(prenom || ' ' || nom) ilike ?", ["%{$search}%"])
                      ->orWhereRaw("(nom || ' ' || prenom) ilike ?", ["%{$search}%"]))
                  ->orWhereHas('classe', fn ($q) => $q->where('nom', 'ilike', "%{$search}%"));
            }))
            // Le matricule identifie l'eleve : le chercher seul evite le bruit
            // que ramene la recherche large ci-dessus (noms, numero, classe).
            ->when(
                $filters['matricule'] ?? null,
                fn ($q, $m) => $q->whereHas('eleve', fn ($q) => $q->where('matricule', 'ilike', "%{$m}%")),
            )
            // Le numero d'inscription est lui aussi un identifiant exact.
            ->when(
                $filters['numero_inscription'] ?? null,
                fn ($q, $n) => $q->where('numero_inscription', 'ilike', "%{$n}%"),
            )
            ->when($filters['classe_id'] ?? null, fn ($q, $id) => $q->where('classe_id', $id))
            ->when($filters['statut_inscription'] ?? null, fn ($q, $s) => $q->where('statut_inscription', $s))
            ->when($filters['statut_paiement'] ?? null, fn ($q, $s) => $q->where('statut_paiement', $s))
            ->when($filters['type_inscription'] ?? null, fn ($q, $t) => $q->where('type_inscription', $t))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Inscription::query()
            ->with(self::RELATIONS)
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->orderBy('numero_inscription', 'asc')
            ->get();
    }

    public function findById(int|string $id): Inscription
    {
        return Inscription::with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $data): Inscription
    {
        return Inscription::create($data);
    }

    public function update(Inscription $inscription, array $data): Inscription
    {
        $inscription->update($data);

        return $inscription->fresh(self::RELATIONS);
    }

    public function delete(Inscription $inscription): void
    {
        $inscription->delete();
    }

    /**
     * Format ENR-001-26 : le suffixe vient de l'annee de debut de l'annee
     * scolaire (et non de l'annee civile), le compteur repart a 001 pour
     * chaque annee. Les inscriptions supprimees comptent, sinon un numero
     * deja emis pourrait etre reattribue.
     */
    public function nextNumeroInscription(int|string $anneeScolaireId): string
    {
        $annee  = AnneeScolaire::findOrFail($anneeScolaireId);
        $suffix = $annee->date_debut->format('y');

        $dernier = Inscription::withTrashed()
            ->where('annee_scolaire_id', $annee->id)
            ->where('numero_inscription', 'like', "ENR-%-{$suffix}")
            ->orderByRaw("CAST(SPLIT_PART(numero_inscription, '-', 2) AS INTEGER) DESC")
            ->value('numero_inscription');

        $next = $dernier ? ((int) explode('-', $dernier)[1]) + 1 : 1;

        return sprintf('ENR-%03d-%s', $next, $suffix);
    }

    public function elevePossedeInscriptionAnterieure(int|string $eleveId, int|string $anneeScolaireId): bool
    {
        return Inscription::withTrashed()
            ->where('eleve_id', $eleveId)
            ->where('annee_scolaire_id', '!=', $anneeScolaireId)
            ->exists();
    }

    /**
     * Une inscription "utilisee" a un encaissement reel derriere elle — pas
     * seulement des mensualites, qui sont generees automatiquement (statut
     * NON_PAYE) dès la creation et existeraient donc toujours, meme sans le
     * moindre paiement.
     */
    public function isUsed(Inscription $inscription): bool
    {
        return $inscription->paiements()->exists()
            || $inscription->mensualites()->whereHas('paiements')->exists();
    }

    /**
     * Inscription active de l'eleve dont l'annee scolaire n'est PAS cloturee.
     *
     * Un eleve ne peut etre reinscrit tant que l'annee scolaire de son
     * inscription en cours n'est pas terminee (statut CLOTURER). On ignore les
     * inscriptions annulees, qui ne bloquent pas une nouvelle inscription.
     *
     * Retourne l'inscription bloquante (avec son annee) ou null.
     */
    public function inscriptionActiveNonCloturee(int|string $eleveId): ?Inscription
    {
        return Inscription::with('anneeScolaire')
            ->where('eleve_id', $eleveId)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE->value)
            ->whereHas('anneeScolaire', function ($q) {
                $q->where('statut', '!=', StatutAnneeScolaire::CLOTURER->value);
            })
            ->first();
    }
}
