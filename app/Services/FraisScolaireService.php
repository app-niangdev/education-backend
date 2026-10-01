<?php

namespace App\Services;

use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\FraisScolaireRepositoryInterface;
use App\Interfaces\FraisScolaireServiceInterface;
use App\Models\AnneeScolaire;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FraisScolaireService implements FraisScolaireServiceInterface
{
    public function __construct(
        private readonly FraisScolaireRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface      $activityLog,
    ) {}

    public function list(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): FraisScolaire
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): FraisScolaire
    {
        // Le formulaire ne propose que l'annee en cours ; on le verifie ici
        // aussi, le client n'etant pas la garde.
        $annee = AnneeScolaire::findOrFail($data['annee_scolaire_id']);

        if (! $annee->en_cours) {
            abort(422, "Un barème ne peut être créé que sur l'année scolaire en cours.");
        }

        if ($this->repository->existsPour($data['annee_scolaire_id'], $data['niveau_id'])) {
            abort(422, 'Un barème existe déjà pour ce niveau sur cette année scolaire.');
        }

        $frais = $this->repository->create($data);
        $frais = $this->repository->findById($frais->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'frais-scolaires',
            description: "Création du barème « {$frais->niveau?->nom} » pour l'année « {$frais->anneeScolaire?->nom} »",
            subject:     $frais,
            newValues:   $frais->toArray(),
        );

        return $frais;
    }

    public function update(int|string $id, array $data, User $authUser): FraisScolaire
    {
        $frais     = $this->repository->findById($id);
        $oldValues = $frais->toArray();

        $anneeScolaireId = $data['annee_scolaire_id'] ?? $frais->annee_scolaire_id;
        $niveauId        = $data['niveau_id']        ?? $frais->niveau_id;

        if ($this->repository->existsPour($anneeScolaireId, $niveauId, $frais->id)) {
            abort(422, 'Un barème existe déjà pour ce niveau sur cette année scolaire.');
        }

        $frais = $this->repository->update($frais, $data);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'frais-scolaires',
            description: "Modification du barème « {$frais->niveau?->nom} » pour l'année « {$frais->anneeScolaire?->nom} »",
            subject:     $frais,
            oldValues:   $oldValues,
            newValues:   $frais->toArray(),
        );

        return $frais;
    }

    public function grilleAnneeEnCours(): Collection
    {
        $annee = AnneeScolaire::where('en_cours', true)->first();

        return $annee
            ? $this->repository->pourAnnee($annee->id)
            : new Collection();
    }

    /**
     * Un bareme ne se cree que sur l'annee scolaire en cours : les annees
     * cloturees sont figees et celles a venir n'ont pas encore de grille. Le
     * formulaire ne propose donc qu'elle, et seulement les niveaux qui n'y ont
     * pas deja un bareme — la combinaison (annee, niveau) etant unique.
     */
    public function referentielsFormulaire(int|string|null $fraisId = null): array
    {
        $frais = $fraisId ? $this->repository->findById($fraisId) : null;

        // En modification, on reste sur l'annee du bareme edite : elle peut
        // differer de l'annee en cours si celle-ci a bascule depuis.
        $annee = $frais
            ? $frais->anneeScolaire
            : AnneeScolaire::where('en_cours', true)->first();

        return [
            'annee'   => $annee,
            'niveaux' => $annee
                ? $this->repository->niveauxSansBareme($annee->id, $frais?->niveau_id)
                : new Collection(),
        ];
    }

    public function delete(int|string $id, User $authUser): void
    {
        $frais = $this->repository->findById($id);

        // FraisScolaire n'est pas en SoftDeletes : delete() est un hard delete,
        // qui libere reellement (annee_scolaire_id, niveau_id) pour un futur
        // bareme — cf. migration drop_soft_deletes_from_frais_scolaires.
        $frais->delete();

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'frais-scolaires',
            description: "Suppression du barème « {$frais->niveau?->nom} » pour l'année « {$frais->anneeScolaire?->nom} »",
            subject:     $frais,
            oldValues:   $frais->toArray(),
        );
    }

    /**
     * Les montants ne vivant plus sur le niveau, la grille se genere en
     * recopiant celle de l'annee scolaire precedente. Sans annee precedente
     * tarifee, on cree les lignes manquantes a zero : elles restent a saisir,
     * mais l'utilisateur n'a pas a les creer une par une.
     *
     * Idempotent : n'insere que les baremes reellement manquants, jamais un
     * doublon — un bareme supprime (hard delete) libere reellement la
     * combinaison (annee_scolaire_id, niveau_id), cf. existsPour.
     */
    public function genererGrille(int|string $anneeScolaireId, User $authUser): int
    {
        $annee = AnneeScolaire::findOrFail($anneeScolaireId);

        // Comme la creation unitaire : seule l'annee en cours se tarife.
        if (! $annee->en_cours) {
            abort(422, "La grille tarifaire ne peut être générée que pour l'année scolaire en cours.");
        }

        return DB::transaction(function () use ($annee, $authUser) {
            // Niveaux deja tarifes sur l'annee cible.
            $niveauxExistants = FraisScolaire::where('annee_scolaire_id', $annee->id)
                ->pluck('niveau_id');

            $niveauxManquants = Niveau::whereNotIn('id', $niveauxExistants)
                ->orderBy('id')
                ->get();

            $anneePrecedente = AnneeScolaire::query()
                ->where('date_debut', '<', $annee->date_debut)
                ->orderByDesc('date_debut')
                ->first();

            // Baremes de reference, indexes par niveau.
            $reference = $anneePrecedente
                ? FraisScolaire::where('annee_scolaire_id', $anneePrecedente->id)->get()->keyBy('niveau_id')
                : collect();

            foreach ($niveauxManquants as $niveau) {
                $modele = $reference->get($niveau->id);

                $this->repository->create([
                    'annee_scolaire_id'    => $annee->id,
                    'niveau_id'            => $niveau->id,
                    'montant_inscription'  => (int) ($modele->montant_inscription ?? 0),
                    'montant_mensualite'   => (int) ($modele->montant_mensualite ?? 0),
                    'nombre_mensualites'   => (int) ($modele->nombre_mensualites ?? FraisScolaire::NOMBRE_MENSUALITES_DEFAUT),
                    'neuvieme_mois_inclus' => (bool) ($modele->neuvieme_mois_inclus ?? false),
                ]);
            }

            $nbCrees = $niveauxManquants->count();
            $origine = $anneePrecedente
                ? "reprise de l'année « {$anneePrecedente->nom} »"
                : 'montants à saisir';

            $this->activityLog->log(
                user:        $authUser,
                action:      'generated',
                module:      'frais-scolaires',
                description: "Génération de la grille tarifaire ({$nbCrees} barème(s), {$origine}) pour l'année « {$annee->nom} »",
                subject:     $annee,
            );

            return $nbCrees;
        });
    }
}
