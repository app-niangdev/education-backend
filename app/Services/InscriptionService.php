<?php

namespace App\Services;

use App\Enums\StatutInscriptionEnum;
use App\Enums\TypeInscriptionEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\EleveServiceInterface;
use App\Interfaces\InscriptionRepositoryInterface;
use App\Interfaces\InscriptionServiceInterface;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\User;
use App\Support\FraisScolaireResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InscriptionService implements InscriptionServiceInterface
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface    $activityLog,
        private readonly EleveServiceInterface          $eleveService,
        private readonly FraisScolaireResolver          $fraisResolver,
    ) {}

    public function list(int $perPage, string $search, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $search, $filters);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(int|string $id): Inscription
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Inscription
    {
        return DB::transaction(function () use ($data, $authUser) {
            $classe = Classe::with('niveau', 'anneeScolaire')->findOrFail($data['classe_id']);

            // La classe porte l'annee scolaire : on ignore toute valeur envoyee
            // par le client pour eviter une inscription sur une autre annee.
            $anneeScolaireId = $classe->annee_scolaire_id;

            // Regle metier : un eleve ne peut etre inscrit deux fois tant que
            // l'annee scolaire de son inscription active n'est pas cloturee.
            // On la re-autorise donc uniquement quand la precedente est terminee.
            $inscriptionBloquante = $this->repository->inscriptionActiveNonCloturee($data['eleve_id']);

            if ($inscriptionBloquante !== null) {
                $annee = $inscriptionBloquante->anneeScolaire?->nom ?? 'en cours';
                abort(422, "Cet élève est déjà inscrit pour l'année scolaire « {$annee} », qui n'est pas encore clôturée. Une réinscription ne sera possible qu'une fois cette année terminée.");
            }

            // Bareme tarifaire de la combinaison (annee scolaire + niveau).
            // Le montant fait foi cote serveur, jamais depuis la requete.
            $bareme  = $this->fraisResolver->baremePour($anneeScolaireId, $classe->niveau_id);
            $montant = (int) $bareme['montant_inscription'];

            $type = $this->repository->elevePossedeInscriptionAnterieure($data['eleve_id'], $anneeScolaireId)
                ? TypeInscriptionEnum::REINSCRIPTION
                : TypeInscriptionEnum::NOUVELLE;

            $inscription = $this->repository->create([
                'numero_inscription'  => $this->repository->nextNumeroInscription($anneeScolaireId),
                'eleve_id'            => $data['eleve_id'],
                'classe_id'           => $classe->id,
                'annee_scolaire_id'   => $anneeScolaireId,
                'type_inscription'    => $type,
                'utilisateur_id'      => $authUser->id,
                'montant_inscription' => $montant,
                'statut_inscription'  => StatutInscriptionEnum::EN_ATTENTE,
                'date_inscription'    => $data['date_inscription'] ?? now()->toDateString(),
            ]);

            // Genere l'echeancier des mensualites (une ligne par mois, due au
            // plus tard le 5) a partir du debut de l'annee scolaire.
            $this->genererMensualites($inscription, $classe->anneeScolaire, $bareme);

            $inscription = $this->repository->findById($inscription->id);

            // La fiche eleve porte une copie de sa classe courante.
            $this->eleveService->synchroniserClasseActuelle($inscription->eleve_id, $classe->id);

            $this->activityLog->log(
                user:        $authUser,
                action:      'created',
                module:      'inscriptions',
                description: "Création de l'inscription « {$inscription->numero_inscription} » ({$type->value}) pour l'élève « {$inscription->eleve?->nom_complet} »",
                subject:     $inscription,
                newValues:   $inscription->toArray(),
            );

            return $inscription;
        });
    }

    public function update(int|string $id, array $data, User $authUser): Inscription
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $inscription = $this->repository->findById($id);
            $oldValues   = $inscription->toArray();

            if ($inscription->estAnnulee()) {
                abort(422, "Impossible de modifier une inscription annulée.");
            }

            $payload = [];

            if (!empty($data['classe_id']) && (int) $data['classe_id'] !== (int) $inscription->classe_id) {
                $classe = Classe::with('niveau')->findOrFail($data['classe_id']);

                if ((int) $classe->annee_scolaire_id !== (int) $inscription->annee_scolaire_id) {
                    abort(422, "La nouvelle classe n'appartient pas à la même année scolaire.");
                }

                // Le tarif suit l'annee scolaire de l'inscription, pas le niveau.
                $nouveauBareme  = $this->fraisResolver->baremePour(
                    $inscription->annee_scolaire_id,
                    $classe->niveau_id,
                );
                $nouveauMontant = (int) $nouveauBareme['montant_inscription'];

                // Un changement de classe ne doit pas rendre le montant du
                // inferieur a ce qui a deja ete encaisse.
                if ($nouveauMontant < $inscription->montant_inscription_paye) {
                    abort(422, "Impossible de changer de classe : le montant d'inscription du nouveau niveau ({$nouveauMontant}) est inférieur au montant déjà versé ({$inscription->montant_inscription_paye}).");
                }

                $payload['classe_id']           = $classe->id;
                $payload['montant_inscription'] = $nouveauMontant;
            }

            if (!empty($data['date_inscription'])) {
                $payload['date_inscription'] = $data['date_inscription'];
            }

            $inscription = $this->repository->update($inscription, $payload);

            // Le montant du a pu changer : le statut de paiement en depend.
            $inscription->synchroniserStatutPaiement();
            $inscription = $this->repository->findById($inscription->id);

            if (isset($payload['classe_id'])) {
                $this->eleveService->synchroniserClasseActuelle($inscription->eleve_id, $payload['classe_id']);
            }

            $this->activityLog->log(
                user:        $authUser,
                action:      'updated',
                module:      'inscriptions',
                description: "Modification de l'inscription « {$inscription->numero_inscription} »",
                subject:     $inscription,
                oldValues:   $oldValues,
                newValues:   $inscription->toArray(),
            );

            return $inscription;
        });
    }

    public function annuler(int|string $id, ?string $motif, User $authUser): Inscription
    {
        return DB::transaction(function () use ($id, $motif, $authUser) {
            $inscription = $this->repository->findById($id);
            $oldValues   = $inscription->toArray();

            if ($inscription->estAnnulee()) {
                abort(422, "Cette inscription est déjà annulée.");
            }

            // Annuler une inscription deja encaissee laisserait des paiements
            // orphelins : il faut d'abord les rembourser/supprimer.
            if ($inscription->montant_inscription_paye > 0) {
                abort(422, "Impossible d'annuler cette inscription : un montant de {$inscription->montant_inscription_paye} a déjà été encaissé.");
            }

            $inscription = $this->repository->update($inscription, [
                'statut_inscription' => StatutInscriptionEnum::ANNULEE,
            ]);

            // L'eleve n'est plus rattache a cette classe.
            $this->eleveService->synchroniserClasseActuelle($inscription->eleve_id, null);

            $description = "Annulation de l'inscription « {$inscription->numero_inscription} »";

            if (filled($motif)) {
                $description .= " — motif : {$motif}";
            }

            $this->activityLog->log(
                user:        $authUser,
                action:      'cancelled',
                module:      'inscriptions',
                description: $description,
                subject:     $inscription,
                oldValues:   $oldValues,
                newValues:   $inscription->toArray(),
            );

            return $inscription;
        });
    }

    /**
     * L'admin supprime sans restriction de statut ni de createur.
     *
     * Le manager et le surveillant, eux, ne peuvent toucher qu'une inscription
     * encore EN_ATTENTE — une fois validee ou annulee, seul l'admin y touche.
     * Le surveillant est en plus limite a ce qu'il a lui-meme saisi : il recoit
     * les familles au guichet, mais ne doit pas pouvoir effacer l'inscription
     * d'un collegue.
     */
    public function delete(int|string $id, User $authUser): void
    {
        $inscription = $this->repository->findById($id);

        $role = $authUser->role?->name;

        if ($role !== 'admin' && !$inscription->estEnAttente()) {
            abort(422, "Seule une inscription en attente peut être supprimée.");
        }

        if ($role === 'supervisor' && (int) $inscription->utilisateur_id !== (int) $authUser->id) {
            abort(403, "Vous ne pouvez supprimer que les inscriptions que vous avez créées.");
        }

        if ($this->repository->isUsed($inscription)) {
            abort(422, "Impossible de supprimer cette inscription : des paiements ou des mensualités y sont rattachés.");
        }

        $this->repository->delete($inscription);

        $this->eleveService->synchroniserClasseActuelle($inscription->eleve_id, null);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'inscriptions',
            description: "Suppression de l'inscription « {$inscription->numero_inscription} »",
            subject:     $inscription,
            oldValues:   $inscription->toArray(),
        );
    }

    /**
     * Donnees de la fiche de renseignement (frais + echeancier) pour le PDF.
     */
    public function dataForFichePdf(int|string $id): array
    {
        $inscription = $this->repository->findById($id);
        $inscription->load(['mensualites' => fn ($q) => $q->orderBy('annee')->orderBy('mois')]);

        $niveauId        = $inscription->classe?->niveau_id;
        $anneeScolaireId = $inscription->annee_scolaire_id;

        // La fiche reste imprimable meme sans bareme saisi : elle affichera
        // simplement les montants comme non definis.
        $bareme = $niveauId
            ? $this->fraisResolver->baremeOuNull($anneeScolaireId, $niveauId)
            : null;

        // Liste des mois a payer : les mensualites reelles si elles existent,
        // sinon l'echeancier theorique calcule depuis le bareme (afin que la
        // fiche affiche toujours les mois de l'annee scolaire).
        if ($inscription->mensualites->isNotEmpty()) {
            $echeancier = $inscription->mensualites->map(fn (Mensualite $m) => [
                'mois'          => $m->mois,
                'annee'         => $m->annee,
                'montant'       => $m->montant_mensualite,
                'date_echeance' => $m->date_echeance,
                'statut'        => $m->statut?->value,
            ])->all();
        } elseif ($bareme && $inscription->anneeScolaire) {
            $echeancier = array_map(fn (array $l) => [
                'mois'          => $l['mois'],
                'annee'         => $l['annee'],
                'montant'       => $l['montant_mensualite'],
                'date_echeance' => $l['date_echeance'],
                'statut'        => null,
            ], $this->fraisResolver->echeancier($inscription->anneeScolaire, $bareme));
        } else {
            $echeancier = [];
        }

        return [
            'inscription' => $inscription,
            'eleve'       => $inscription->eleve,
            'classe'      => $inscription->classe,
            'annee'       => $inscription->anneeScolaire,
            'bareme'      => $bareme,
            'echeancier'  => $echeancier,
        ];
    }

    /**
     * Cree les mensualites de l'inscription a partir de l'echeancier resolu.
     * Idempotent : les mois deja presents ne sont pas dupliques.
     */
    private function genererMensualites(Inscription $inscription, $anneeScolaire, array $bareme): void
    {
        $echeancier = $this->fraisResolver->echeancier($anneeScolaire, $bareme);

        foreach ($echeancier as $ligne) {
            Mensualite::firstOrCreate(
                [
                    'inscription_id' => $inscription->id,
                    'mois'           => $ligne['mois'],
                    'annee'          => $ligne['annee'],
                ],
                [
                    'date_echeance'      => $ligne['date_echeance']->toDateString(),
                    'montant_mensualite' => $ligne['montant_mensualite'],
                ],
            );
        }
    }
}
