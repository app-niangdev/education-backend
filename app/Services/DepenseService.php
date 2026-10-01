<?php

namespace App\Services;

use App\Enums\StatutDepenseEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\DepenseRepositoryInterface;
use App\Interfaces\DepenseServiceInterface;
use App\Models\AnneeScolaire;
use App\Models\Depense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DepenseService implements DepenseServiceInterface
{
    public function __construct(
        private readonly DepenseRepositoryInterface  $repository,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function list(int $perPage, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $this->normaliserFiltres($filters));
    }

    public function all(array $filters = []): Collection
    {
        return $this->repository->all($this->normaliserFiltres($filters));
    }

    public function find(int|string $id): Depense
    {
        return $this->repository->findById($id);
    }

    public function create(array $data, User $authUser): Depense
    {
        $data['annee_scolaire_id'] ??= $this->anneeEnCoursOuEchoue()->id;

        // L'auteur de la saisie vient du jeton, jamais de la requete.
        $data['utilisateur_id'] = $authUser->id;

        // Toute depense naît en attente, y compris celle d'un manager : la
        // validation est un acte distinct de la saisie, et c'est ce qui la
        // rend tracable. Le statut n'est pas « fillable » : on le force ici.
        $data['statut'] = StatutDepenseEnum::EN_ATTENTE;

        $this->refuserSiAnneeCloturee($data['annee_scolaire_id']);

        $depense = $this->repository->findById($this->repository->create($data)->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'depenses',
            description: "Création de la dépense « {$depense->libelle} » ({$depense->montant} FCFA)",
            subject:     $depense,
            newValues:   $depense->toArray(),
        );

        return $depense;
    }

    public function update(int|string $id, array $data, User $authUser): Depense
    {
        $depense   = $this->repository->findById($id);
        $oldValues = $depense->toArray();

        // Une depense ne change pas d'auteur en etant corrigee.
        unset($data['utilisateur_id']);

        $this->refuserSiAnneeCloturee($data['annee_scolaire_id'] ?? $depense->annee_scolaire_id);

        $depense = $this->repository->update($depense, $data);

        // Corriger une depense annule la decision prise : elle repart en
        // attente. Sans cela, un montant valide pourrait etre releve apres
        // coup — la validation ne porterait plus sur ce qui est en base — et
        // une depense refusee deviendrait effective par simple retouche.
        if (!$depense->statut->attendUneDecision()) {
            $depense->forceFill([
                'statut'        => StatutDepenseEnum::EN_ATTENTE,
                'validateur_id' => null,
                'valide_le'     => null,
                'motif_refus'   => null,
            ])->save();

            $depense = $this->repository->findById($depense->id);
        }

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'depenses',
            description: "Modification de la dépense « {$depense->libelle} »",
            subject:     $depense,
            oldValues:   $oldValues,
            newValues:   $depense->toArray(),
        );

        return $depense;
    }

    /**
     * Le manager engage l'etablissement : la depense entre alors dans les
     * totaux et le bilan. Jusque-la, elle n'etait qu'une annonce de sortie.
     */
    public function valider(int|string $id, User $authUser): Depense
    {
        return $this->trancher($id, StatutDepenseEnum::VALIDEE, $authUser);
    }

    /**
     * Le refus n'efface pas la ligne : il la marque et conserve son motif.
     * Le tresorier voit ce qui a ete ecarte, et pourquoi.
     */
    public function refuser(int|string $id, string $motif, User $authUser): Depense
    {
        return $this->trancher($id, StatutDepenseEnum::REFUSEE, $authUser, $motif);
    }

    /**
     * Le passage d'une depense en attente a une decision definitive.
     *
     * On refuse de trancher deux fois : une depense deja validee entrerait
     * sinon en double dans l'historique, et une refusee changerait d'avis sans
     * trace. Corriger une decision passe par la modification de la depense,
     * qui la remet explicitement en attente.
     */
    private function trancher(
        int|string $id,
        StatutDepenseEnum $statut,
        User $authUser,
        ?string $motif = null,
    ): Depense {
        $depense   = $this->repository->findById($id);
        $oldValues = $depense->toArray();

        if (!$depense->statut->attendUneDecision()) {
            abort(422, "Cette dépense a déjà été {$depense->statut->libelle()} : elle n'attend plus de décision.");
        }

        $this->refuserSiAnneeCloturee($depense->annee_scolaire_id);

        // forceFill : ces colonnes ne sont pas « fillable », precisement pour
        // qu'aucune requete ne puisse les poser en passant par la saisie.
        $depense->forceFill([
            'statut'        => $statut,
            'validateur_id' => $authUser->id,
            'valide_le'     => now(),
            'motif_refus'   => $motif,
        ])->save();

        $depense = $this->repository->findById($depense->id);

        $this->activityLog->log(
            user:        $authUser,
            action:      $statut === StatutDepenseEnum::VALIDEE ? 'validated' : 'rejected',
            module:      'depenses',
            description: $statut === StatutDepenseEnum::VALIDEE
                ? "Validation de la dépense « {$depense->libelle} » ({$depense->montant} FCFA)"
                : "Refus de la dépense « {$depense->libelle} » ({$depense->montant} FCFA) : {$motif}",
            subject:     $depense,
            oldValues:   $oldValues,
            newValues:   $depense->toArray(),
        );

        return $depense;
    }

    public function delete(int|string $id, User $authUser): void
    {
        $depense   = $this->repository->findById($id);
        $oldValues = $depense->toArray();

        $this->refuserSiAnneeCloturee($depense->annee_scolaire_id);

        $this->repository->delete($depense);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'depenses',
            description: "Suppression de la dépense « {$depense->libelle} » ({$depense->montant} FCFA)",
            subject:     $depense,
            oldValues:   $oldValues,
        );
    }

    public function totaux(array $filters): array
    {
        return $this->repository->totaux($this->normaliserFiltres($filters));
    }

    public function moisAnneeEnCours(): array
    {
        $annee = AnneeScolaire::where('en_cours', true)->first();

        if ($annee === null) {
            return ['annee_scolaire' => null, 'mois' => []];
        }

        // Un seul agregat pour toute l'annee : boucler mois par mois ferait
        // autant de requetes qu'il y a de mois.
        $totaux = Depense::query()
            ->where('annee_scolaire_id', $annee->id)
            ->select(
                DB::raw('EXTRACT(MONTH FROM date_depense)::int as mois'),
                DB::raw('EXTRACT(YEAR FROM date_depense)::int as annee'),
                DB::raw('SUM(montant) as total'),
                DB::raw('COUNT(*) as nombre'),
            )
            ->groupBy('mois', 'annee')
            ->get()
            ->keyBy(fn ($ligne) => "{$ligne->annee}-{$ligne->mois}");

        $debut = CarbonImmutable::parse($annee->date_debut)->startOfMonth();
        $fin   = CarbonImmutable::parse($annee->date_fin)->startOfMonth();
        $mois  = [];

        // Tous les mois de l'annee scolaire sont listes, y compris ceux sans
        // depense : le filtre doit les proposer, avec un total a zero.
        for ($courant = $debut; $courant <= $fin; $courant = $courant->addMonth()) {
            $numeroMois = (int) $courant->format('n');
            $anneeCivile = (int) $courant->format('Y');
            $ligne = $totaux->get("{$anneeCivile}-{$numeroMois}");

            $mois[] = [
                'mois'    => $numeroMois,
                'annee'   => $anneeCivile,
                'libelle' => $this->libelleMois($numeroMois) . ' ' . $anneeCivile,
                'total'   => (int) ($ligne->total ?? 0),
                'nombre'  => (int) ($ligne->nombre ?? 0),
            ];
        }

        return ['annee_scolaire' => $annee, 'mois' => $mois];
    }

    /**
     * Sans annee scolaire explicite, les filtres portent sur l'annee en cours :
     * le module montre la gestion courante, pas tout l'historique.
     */
    private function normaliserFiltres(array $filters): array
    {
        if (($filters['toutes_annees'] ?? false) === true) {
            unset($filters['annee_scolaire_id']);

            return $filters;
        }

        $filters['annee_scolaire_id'] ??= AnneeScolaire::where('en_cours', true)->value('id');

        return $filters;
    }

    private function anneeEnCoursOuEchoue(): AnneeScolaire
    {
        $annee = AnneeScolaire::where('en_cours', true)->first();

        if ($annee === null) {
            abort(422, "Aucune année scolaire n'est en cours : impossible d'enregistrer une dépense.");
        }

        return $annee;
    }

    /**
     * Une annee cloturee est un exercice arrete : ses depenses ne bougent plus.
     */
    private function refuserSiAnneeCloturee(int|string|null $anneeScolaireId): void
    {
        if ($anneeScolaireId === null) {
            return;
        }

        $annee = AnneeScolaire::find($anneeScolaireId);

        if ($annee?->estCloturee()) {
            abort(422, "L'année scolaire « {$annee->nom} » est clôturée : ses dépenses ne peuvent plus être modifiées.");
        }
    }

    private function libelleMois(int $mois): string
    {
        return [
            1 => 'Janvier',   2 => 'Février',  3 => 'Mars',      4 => 'Avril',
            5 => 'Mai',       6 => 'Juin',     7 => 'Juillet',   8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ][$mois] ?? '';
    }
}
