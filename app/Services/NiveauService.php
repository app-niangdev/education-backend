<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\NiveauRepositoryInterface;
use App\Interfaces\NiveauServiceInterface;
use App\Models\AnneeScolaire;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NiveauService implements NiveauServiceInterface
{
    public function __construct(
        private readonly NiveauRepositoryInterface   $repository,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Ce qu'affiche l'interface unifiee : chaque niveau avec son tarif de
     * l'annee en cours. Les annees passees restent en base pour l'historique
     * de facturation, mais ne se pilotent plus depuis cet ecran — seule
     * l'annee en cours se tarife.
     *
     * `annee` est nulle tant qu'aucune annee n'est en cours : les niveaux
     * s'affichent alors sans tarif, et se creent quand meme.
     */
    public function grilleAnneeEnCours(): array
    {
        $annee = AnneeScolaire::where('en_cours', true)->first();

        return [
            'annee'   => $annee,
            'niveaux' => $annee
                ? $this->repository->avecBaremeAnnee($annee->id)
                : $this->repository->all(),
        ];
    }

    public function find(int|string $id): Niveau
    {
        return $this->repository->findById($id);
    }

    /**
     * Le niveau et son tarif se creent d'un seul geste : un niveau sans bareme
     * bloque toute inscription (FraisScolaireResolver abort 422), les separer
     * laissait donc un etat inutilisable entre les deux etapes.
     *
     * Les montants restent facultatifs : sans annee scolaire en cours il n'y a
     * rien a tarifer, et le parametrage initial de l'ecole doit rester
     * possible. Le bareme s'ajoute alors plus tard, par modification.
     */
    public function create(array $data, User $authUser): Niveau
    {
        return DB::transaction(function () use ($data, $authUser) {
            $bareme = $this->extraireBareme($data);

            // L'ordre situe le niveau dans son cycle (progression pour le
            // passage de classe). Il n'est pas saisi : on place le nouveau
            // niveau a la suite du dernier de son cycle. Le verrou evite que
            // deux creations simultanees se disputent la meme place, ce que la
            // contrainte unique (cycle, ordre) rejetterait sinon.
            $data['ordre'] = $this->prochainOrdre($data['cycle']);

            $niveau = $this->repository->create($data);

            $annee = $this->anneeEnCours();

            // Le bareme ne se cree qu'avec une annee en cours a tarifer. Sans
            // elle le niveau existe seul : c'est le cas du parametrage initial.
            if ($bareme !== null && $annee !== null) {
                $niveau->fraisScolaires()->create([
                    'annee_scolaire_id' => $annee->id,
                    ...$bareme,
                ]);

                $niveau->load([
                    'fraisScolaire' => fn ($q) => $q->where('annee_scolaire_id', $annee->id),
                ]);
            }

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'niveaux',
                description: "Création du niveau « {$niveau->nom} »"
                    . ($niveau->fraisScolaire ? " et de son barème {$annee?->nom}" : ''),
                subject:     $niveau,
                newValues:   $niveau->toArray(),
            );

            return $niveau;
        });
    }

    /**
     * Detache les champs tarifaires du payload : ils ne vont pas sur le niveau
     * (qui ne porte aucun montant) mais sur la grille frais_scolaires.
     *
     * Retourne null si le formulaire n'a envoye aucun montant — creation d'un
     * niveau seul, faute d'annee scolaire en cours.
     */
    private function extraireBareme(array &$data): ?array
    {
        $champs = [
            'montant_inscription',
            'montant_mensualite',
            'nombre_mensualites',
            'neuvieme_mois_inclus',
        ];

        $bareme = [];

        foreach ($champs as $champ) {
            if (array_key_exists($champ, $data)) {
                $bareme[$champ] = $data[$champ];
                unset($data[$champ]);
            }
        }

        // Les montants vont par paire : l'inscription seule ne fait pas un
        // bareme exploitable, le formulaire les envoie toujours ensemble.
        if (! isset($bareme['montant_inscription'], $bareme['montant_mensualite'])) {
            return null;
        }

        $bareme['nombre_mensualites'] ??= FraisScolaire::NOMBRE_MENSUALITES_DEFAUT;
        $bareme['neuvieme_mois_inclus'] ??= false;

        return $bareme;
    }

    private function anneeEnCours(): ?AnneeScolaire
    {
        return AnneeScolaire::where('en_cours', true)->first();
    }

    /**
     * Prochaine place libre dans le cycle : dernier ordre + 1, ou 1 si vide.
     *
     * On verrouille la derniere ligne du cycle (lockForUpdate) plutot que
     * d'agreger MAX(ordre) sous FOR UPDATE, que Postgres interdit. Deux
     * creations concurrentes sur le meme cycle se serialisent ainsi sur cette
     * ligne, ce qui evite qu'elles calculent le meme ordre.
     */
    private function prochainOrdre(string $cycle): int
    {
        $dernier = Niveau::query()
            ->where('cycle', $cycle)
            ->orderByDesc('ordre')
            ->lockForUpdate()
            ->first();

        return (int) ($dernier->ordre ?? 0) + 1;
    }

    /**
     * Modifie le niveau et, dans la meme transaction, son bareme de l'annee en
     * cours : l'interface unifiee les presente comme un seul objet, ils se
     * modifient donc ensemble. Le bareme est cree s'il n'existait pas encore
     * (niveau saisi avant l'ouverture de l'annee scolaire).
     */
    public function update(int|string $id, array $data, User $authUser): Niveau
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $niveau    = $this->repository->findById($id);
            $oldValues = $niveau->toArray();

            $bareme = $this->extraireBareme($data);

            // L'ordre n'est pas saisi. S'il change de cycle, sa place dans
            // l'ancien cycle n'a plus de sens : on le repositionne a la fin du
            // nouveau, sinon il conserve la sienne.
            $nouveauCycle = $data['cycle'] ?? $niveau->cycle?->value;

            if ($nouveauCycle !== $niveau->cycle?->value) {
                $data['ordre'] = $this->prochainOrdre($nouveauCycle);
            } else {
                unset($data['ordre']);
            }

            $niveau = $this->repository->update($niveau, $data);

            $annee = $this->anneeEnCours();

            if ($bareme !== null && $annee !== null) {
                // Les annees passees sont figees : seul le bareme de l'annee en
                // cours se modifie ici. updateOrCreate couvre les deux cas d'un
                // meme geste, le couple (annee, niveau) etant unique.
                $niveau->fraisScolaires()->updateOrCreate(
                    ['annee_scolaire_id' => $annee->id],
                    $bareme,
                );

                $niveau->load([
                    'fraisScolaire' => fn ($q) => $q->where('annee_scolaire_id', $annee->id),
                ]);
            }

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'niveaux',
                description: "Modification du niveau « {$niveau->nom} »"
                    . ($bareme !== null && $annee !== null ? " et de son barème {$annee->nom}" : ''),
                subject:     $niveau,
                oldValues:   $oldValues,
                newValues:   $niveau->toArray(),
            );

            return $niveau;
        });
    }

    /**
     * Le bareme suit le niveau : l'interface unifiee les cree ensemble, elle
     * les supprime ensemble. Seules les classes retiennent la suppression —
     * elles portent des eleves, et un niveau efface les laisserait orphelines.
     *
     * Les baremes partent en soft delete, l'historique de facturation des
     * annees passees reste donc consultable et restaurable.
     */
    public function delete(int|string $id, User $authUser): void
    {
        DB::transaction(function () use ($id, $authUser) {
            $niveau = $this->repository->findById($id);

            if ($this->repository->isUsed($niveau)) {
                abort(422, "Impossible de supprimer ce niveau : des classes y sont rattachées.");
            }

            $oldValues = $niveau->toArray();

            $niveau->fraisScolaires()->delete();
            $this->repository->delete($niveau);

            $this->activityLog->log(
                user:        $authUser,
                action:      'deleted',
                module:      'niveaux',
                description: "Suppression du niveau « {$niveau->nom} » et de ses barèmes",
                subject:     $niveau,
                oldValues:   $oldValues,
            );
        });
    }
}
