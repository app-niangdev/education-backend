<?php

namespace App\Repositories;

use App\Interfaces\TuteurRepositoryInterface;
use App\Models\Tuteur;
use Illuminate\Database\Eloquent\Collection;

class TuteurRepository implements TuteurRepositoryInterface
{
    public function all(): Collection
    {
        return Tuteur::query()->orderBy('nom')->orderBy('prenom')->get();
    }

    public function findById(int|string $id): Tuteur
    {
        return Tuteur::findOrFail($id);
    }

    public function findByNin(string $nin): ?Tuteur
    {
        return Tuteur::where('nin', $nin)->first();
    }

    /**
     * L'annuaire consulte au moment d'inscrire un eleve : l'agent y retrouve
     * le tuteur d'une fratrie deja connue plutot que de le resaisir.
     *
     * La recherche porte aussi sur le NIN et le telephone, parce que c'est
     * souvent la seule chose que la famille presente au guichet. Le numero est
     * normalise avant comparaison (voir Tuteur::normaliserTelephone) : « +221
     * 77 123 45 67 » doit retrouver la fiche enregistree « 771234567 ».
     *
     * On remonte le nombre d'eleves rattaches : c'est ce qui permet a l'agent
     * de distinguer deux homonymes — celui qui a deja trois enfants dans
     * l'ecole est rarement le bon candidat pour un premier inscrit.
     */
    public function rechercher(string $recherche, int $limite = 10): Collection
    {
        $recherche = trim($recherche);

        if ($recherche === '') {
            return new Collection();
        }

        $telephone = Tuteur::normaliserTelephone($recherche);

        return Tuteur::query()
            ->withCount('eleves')
            ->where(function ($q) use ($recherche, $telephone) {
                $q->where('nom', 'ilike', "%{$recherche}%")
                    ->orWhere('prenom', 'ilike', "%{$recherche}%")
                    ->orWhere('nin', 'ilike', "%{$recherche}%")
                    ->orWhere('email', 'ilike', "%{$recherche}%");

                // Ecarte le cas d'une saisie sans aucun chiffre, qui
                // ramenerait « %% » et donc toute la table.
                if (filled($telephone)) {
                    $q->orWhere('telephone_principal', 'ilike', "%{$telephone}%")
                        ->orWhere('telephone_secondaire', 'ilike', "%{$telephone}%");
                }
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit($limite)
            ->get();
    }

    public function create(array $data): Tuteur
    {
        return Tuteur::create($data);
    }

    public function update(Tuteur $tuteur, array $data): Tuteur
    {
        $tuteur->update($data);

        return $tuteur->fresh();
    }
}
