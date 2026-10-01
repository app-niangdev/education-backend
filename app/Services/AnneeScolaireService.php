<?php

namespace App\Services;

use App\Enums\StatutAnneeScolaire;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\AnneeScolaireRepositoryInterface;
use App\Interfaces\AnneeScolaireServiceInterface;
use App\Models\AnneeScolaire;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AnneeScolaireService implements AnneeScolaireServiceInterface
{
    public function __construct(
        private readonly AnneeScolaireRepositoryInterface $repository,
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

    public function find(int|string $id): AnneeScolaire
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): AnneeScolaire
    {
        // Une annee creee « En cours » devient l'annee active : c'est le meme
        // couplage statut/en_cours qu'en modification. La case `en_cours` du
        // formulaire reste acceptee pour l'amorcage.
        $activer = (bool) ($data['en_cours'] ?? false)
            || ($data['statut'] ?? null) === StatutAnneeScolaire::ENCOURS->value;
        unset($data['en_cours']);

        // Les deux champs restent solidaires : activer une annee la met « En
        // cours », sinon l'interface afficherait « À venir » pour l'annee que
        // tous les ecrans utilisent.
        if ($activer) {
            $data['statut'] = StatutAnneeScolaire::ENCOURS->value;
        }

        $annee = DB::transaction(function () use ($data, $activer) {
            if ($activer) {
                // Fermer l'annee active AVANT d'ouvrir la nouvelle : l'index
                // unique refuse deux lignes actives simultanement.
                $this->repository->desactiverAutresAnnees();
            }

            $annee = $this->repository->create($data);

            // `en_cours` est hors de $fillable : la bascule passe par forceFill.
            return $activer ? $this->repository->activer($annee) : $annee;
        });

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'annees-scolaires',
            description: "Création de l'année scolaire « {$annee->nom} »",
            subject:     $annee,
            newValues:   $annee->toArray(),
        );

        return $annee;
    }

    public function update(int|string $id, array $data, User $authUser): AnneeScolaire
    {
        $annee    = $this->repository->findById($id);
        $oldValues = $annee->toArray();

        // `statut` et `en_cours` decrivent la meme realite : le premier est
        // affiche, le second pilote 21 requetes de l'application. Les laisser
        // diverger produit une annee « En cours » que tous les ecrans ignorent.
        // La bascule se fait donc ici, dans l'ordre impose par l'index unique :
        // fermer l'annee active AVANT d'ouvrir la nouvelle.
        $nouveauStatut = $data['statut'] ?? $annee->statut?->value;
        $devientActive = $nouveauStatut === StatutAnneeScolaire::ENCOURS->value;

        $annee = DB::transaction(function () use ($annee, $data, $devientActive) {
            if ($devientActive) {
                $this->repository->desactiverAutresAnnees($annee);
            }

            $annee = $this->repository->update($annee, $data);

            // Une annee qui quitte ENCOURS ne doit plus etre l'annee active.
            return $devientActive
                ? $this->repository->activer($annee)
                : $this->repository->desactiver($annee);
        });

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'annees-scolaires',
            description: "Modification de l'année scolaire « {$annee->nom} »",
            subject:     $annee,
            oldValues:   $oldValues,
            newValues:   $annee->toArray(),
        );

        return $annee;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $annee = $this->repository->findById($id);

        // L'annee en cours est le pivot de l'application : la supprimer
        // laisserait les ecrans sans annee de reference. On la protege avant
        // meme de regarder ses dependances.
        if ($annee->en_cours) {
            abort(422, "Impossible de supprimer l'année scolaire en cours. Clôturez-la ou activez une autre année d'abord.");
        }

        $dependances = $this->repository->dependances($annee);

        if ($dependances !== []) {
            abort(422, sprintf(
                "Impossible de supprimer cette année scolaire : elle contient %s.",
                $this->enumerer($dependances),
            ));
        }

        $this->repository->delete($annee);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'annees-scolaires',
            description: "Suppression de l'année scolaire « {$annee->nom} »",
            subject:     $annee,
            oldValues:   $annee->toArray(),
        );
    }

    /** « 3 périodes, 2 classes et 5 bulletins » */
    private function enumerer(array $libelles): string
    {
        if (count($libelles) === 1) {
            return $libelles[0];
        }

        $dernier = array_pop($libelles);

        return implode(', ', $libelles) . " et {$dernier}";
    }
}
