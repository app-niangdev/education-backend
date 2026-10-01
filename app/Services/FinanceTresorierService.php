<?php

namespace App\Services;

use App\Enums\PieceJointeEnum;
use App\Enums\StatutInscriptionEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\FinanceTresorierRepositoryInterface;
use App\Interfaces\FinanceTresorierServiceInterface;
use App\Interfaces\NotificationPaiementServiceInterface;
use App\Models\Eleve;
use App\Models\FactureMensualite;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinanceTresorierService implements FinanceTresorierServiceInterface
{
    public function __construct(
        private readonly FinanceTresorierRepositoryInterface  $repository,
        private readonly ActivityLogServiceInterface          $activityLog,
        private readonly NotificationPaiementServiceInterface $notificationPaiement,
    ) {}

    /**
     * Previent la famille qu'un versement a ete enregistre.
     *
     * Appele DANS la transaction d'encaissement : le message et le paiement
     * apparaissent ensemble, ou pas du tout. Le service de notification avale
     * ses propres erreurs — une messagerie indisponible ne doit jamais
     * empecher un tresorier de prendre l'argent d'une famille.
     */
    private function notifierFamille(?Eleve $eleve, array $paiement): void
    {
        $this->notificationPaiement->notifier($eleve, $paiement);
    }

    /** « Mensualité de Mars 2026 » : ce que la famille lit dans son message. */
    private function libelleMensualite(Mensualite $mensualite): string
    {
        $mois = [
            1 => 'Janvier',   2 => 'Février',  3 => 'Mars',      4 => 'Avril',
            5 => 'Mai',       6 => 'Juin',     7 => 'Juillet',   8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return sprintf(
            'Mensualité de %s %d',
            $mois[$mensualite->mois] ?? $mensualite->mois,
            $mensualite->annee,
        );
    }

    /**
     * Regle metier : une inscription ne devient VALIDEE que si un versement
     * strictement positif est encaisse. Le statut de paiement qui en decoule
     * est PARTIEL, ou PAYE lorsque la totalite du montant est reglee.
     */
    public function validerInscription(int|string $inscriptionId, array $data, User $authUser): Inscription
    {
        return DB::transaction(function () use ($inscriptionId, $data, $authUser) {
            $inscription = $this->repository->findInscriptionForUpdate($inscriptionId);
            $oldValues   = $inscription->toArray();

            if ($inscription->estAnnulee()) {
                abort(422, "Impossible de valider une inscription annulée.");
            }

            $montant = (int) $data['montant'];

            if ($montant <= 0) {
                abort(422, "Le montant versé doit être strictement supérieur à 0 pour valider l'inscription.");
            }

            // Le montant du provient du niveau via la classe : un versement ne
            // peut jamais depasser ce qu'il reste a payer.
            $reste = $inscription->montant_inscription_restant;

            if ($reste <= 0) {
                abort(422, "Cette inscription est déjà intégralement payée.");
            }

            if ($montant > $reste) {
                abort(422, "Le montant versé ({$montant}) dépasse le reste à payer ({$reste}).");
            }

            $paiement = $this->repository->createPaiement([
                'inscription_id'     => $inscription->id,
                'utilisateur_id'     => $authUser->id,
                'numero_recu'        => $this->repository->nextNumeroRecu($inscription->annee_scolaire_id),
                'montant'            => $montant,
                'mode_paiement'      => $data['mode_paiement'],
                'numero_transaction' => $data['numero_transaction'] ?? null,
                'date_paiement'      => $data['date_paiement'] ?? now()->toDateString(),
            ]);

            // Recharger les paiements pour que les accesseurs calcules voient
            // le versement qu'on vient d'inserer.
            $inscription->load('paiements');

            $inscription->forceFill([
                'statut_inscription' => StatutInscriptionEnum::VALIDEE,
                'statut_paiement'    => $inscription->statutPaiementCalcule(),
            ])->save();

            $inscription = $this->repository->findInscriptionForUpdate($inscription->id);

            // Expose le versement qui vient d'etre cree pour que le tresorier
            // puisse imprimer son justificatif dans la foulee, sans le rechercher.
            $inscription->setAttribute('paiement_cree', $paiement);

            $this->activityLog->log(
                user:        $authUser,
                action:      'validated',
                module:      'finance-tresorier',
                description: sprintf(
                    "Validation de l'inscription « %s » — versement de %d (reçu %s), statut de paiement : %s",
                    $inscription->numero_inscription,
                    $montant,
                    $paiement->numero_recu,
                    $inscription->statut_paiement->value,
                ),
                subject:     $inscription,
                oldValues:   $oldValues,
                newValues:   $inscription->toArray(),
            );

            $this->notifierFamille($inscription->eleve, [
                'libelle'  => "Frais d'inscription",
                'montant'  => $montant,
                'reste'    => (int) $inscription->montant_inscription_restant,
                'estSolde' => (int) $inscription->montant_inscription_restant <= 0,
                'numero'   => $paiement->numero_recu,
                'type'     => PieceJointeEnum::RECU_INSCRIPTION,
                'pieceId'  => $paiement->id,
            ]);

            return $inscription;
        });
    }

    public function listAEncaisser(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginateAEncaisser($perPage, $search);
    }

    public function listPaiements(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginatePaiements($perPage, $search);
    }

    public function findPaiement(int|string $id): PaiementInscription
    {
        return $this->repository->findPaiementById($id);
    }

    /**
     * Justificatif d'un versement sur inscription. Le document emis depend du
     * solde de l'inscription apres ce versement : recu si elle est integralement
     * reglee, decharge tant qu'il reste un solde exigible.
     */
    public function dataForRecuInscription(int|string $paiementId): array
    {
        $paiement    = $this->repository->findPaiementById($paiementId);
        $inscription = $paiement->inscription;

        $montantDu = (int) ($inscription?->montant_inscription ?? 0);
        $totalPaye = (int) ($inscription?->montant_inscription_paye ?? 0);
        $reste     = (int) ($inscription?->montant_inscription_restant ?? 0);

        return [
            'paiement'     => $paiement,
            'inscription'  => $inscription,
            'eleve'        => $inscription?->eleve,
            'classe'       => $inscription?->classe,
            'annee'        => $inscription?->anneeScolaire,
            'libelleObjet' => "Frais d'inscription",
            'montantDu'    => $montantDu,
            'totalPaye'    => $totalPaye,
            'reste'        => $reste,
            'estSolde'     => $reste <= 0,
        ];
    }

    // ─── Mensualites ──────────────────────────────────────────────────────────

    public function listInscriptionsMensualites(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginateInscriptionsAvecMensualites($perPage, $search);
    }

    /**
     * Versement global reparti sur les mensualites non soldees d'une inscription,
     * des plus anciennes aux plus recentes. Chaque mois touche recoit son propre
     * PaiementMensualite (tracabilite du recu) ; un mois est soldé avant de
     * passer au suivant. Le reliquat qui depasse le total du est refuse.
     */
    public function payerMensualitesReparti(int|string $inscriptionId, array $data, User $authUser): Inscription
    {
        return DB::transaction(function () use ($inscriptionId, $data, $authUser) {
            $inscription = $this->repository->findInscriptionAvecMensualitesForUpdate($inscriptionId);

            if ($inscription->estAnnulee()) {
                abort(422, "Impossible d'encaisser les mensualités d'une inscription annulée.");
            }

            $montant = (int) $data['montant'];

            if ($montant <= 0) {
                abort(422, "Le montant versé doit être strictement supérieur à 0.");
            }

            // Mensualites non soldees, des plus anciennes aux plus recentes.
            $aSolder = $inscription->mensualites
                ->filter(fn (Mensualite $m) => $m->reste > 0)
                ->sortBy(fn (Mensualite $m) => sprintf('%04d%02d', $m->annee, $m->mois))
                ->values();

            $resteTotal = (int) $aSolder->sum(fn (Mensualite $m) => $m->reste);

            if ($resteTotal <= 0) {
                abort(422, "Toutes les mensualités de cet élève sont déjà soldées.");
            }

            if ($montant > $resteTotal) {
                abort(422, "Le montant versé ({$montant}) dépasse le reste total à payer ({$resteTotal}).");
            }

            $anneeScolaireId = $inscription->annee_scolaire_id;
            $restant         = $montant;
            $moisTouches     = [];
            $paiementsCrees  = [];

            foreach ($aSolder as $mensualite) {
                if ($restant <= 0) {
                    break;
                }

                // On impute au plus le reste du mois, puis on passe au suivant.
                $aImputer = min($restant, $mensualite->reste);

                $paiementsCrees[] = $this->repository->createPaiementMensualite([
                    'mensualite_id'      => $mensualite->id,
                    'utilisateur_id'     => $authUser->id,
                    'numero_recu'        => $this->repository->nextNumeroRecuMensualite($anneeScolaireId),
                    'montant'            => $aImputer,
                    'mode_paiement'      => $data['mode_paiement'],
                    'numero_transaction' => $data['numero_transaction'] ?? null,
                    'date_paiement'      => $data['date_paiement'] ?? now()->toDateString(),
                ]);

                $mensualite->load('paiements');
                $mensualite->synchroniserStatut();

                $moisTouches[] = sprintf('%02d/%d', $mensualite->mois, $mensualite->annee);
                $restant -= $aImputer;
            }

            $inscription = $this->repository->findInscriptionAvecMensualitesForUpdate($inscription->id);

            // Un versement reparti genere un recu par mois touche : on les expose
            // tous pour que le tresorier imprime chaque justificatif.
            $inscription->setAttribute('paiements_crees', $paiementsCrees);

            $this->activityLog->log(
                user:        $authUser,
                action:      'paid',
                module:      'finance-tresorier',
                description: sprintf(
                    "Versement réparti de %d sur l'inscription « %s » — mois soldés/complétés : %s",
                    $montant,
                    $inscription->numero_inscription,
                    implode(', ', $moisTouches),
                ),
                subject:     $inscription,
            );

            // Un seul message, malgre les N recus generes : la famille a fait
            // UN versement, en recevoir cinq accuses pour cinq mois imputes
            // serait illisible. Le justificatif joint est celui du dernier mois
            // touche ; les autres restent accessibles depuis l'espace scolarite.
            $dernier = end($paiementsCrees) ?: null;

            if ($dernier !== null) {
                $this->notifierFamille($inscription->eleve, [
                    'libelle'  => 'Mensualités (' . implode(', ', $moisTouches) . ')',
                    'montant'  => $montant,
                    'reste'    => (int) $restant,
                    // Le versement est « solde » lorsqu'il a ete impute en
                    // entier : rien n'est reste sans affectation.
                    'estSolde' => $restant <= 0,
                    'numero'   => $dernier->numero_recu,
                    'type'     => PieceJointeEnum::RECU_MENSUALITE,
                    'pieceId'  => $dernier->id,
                ]);
            }

            return $inscription;
        });
    }

    // ─── Facture couvrant plusieurs mois ──────────────────────────────────────

    /**
     * Encaisse en une seule fois plusieurs mois sous un document unique.
     *
     * Deux modes de saisie, une seule sortie : une FactureMensualite portant le
     * numero, le mode de paiement et la date, et autant de PaiementMensualite
     * que de mois touches (la ligne comptable par mois reste exacte, seule
     * l'edition du justificatif est mutualisee).
     *
     *  - AUTOMATIQUE : le montant recu est impute en cascade du mois le plus
     *    ancien au plus recent. Un versement de 50 000 pour une mensualite de
     *    20 000 solde deux mois et laisse 10 000 d'avance sur le troisieme, qui
     *    passe alors en PARTIEL — c'est le cas courant au guichet.
     *  - SELECTION : le tresorier a coche les mois et fixe le montant impute sur
     *    chacun ; on se contente de verifier que rien ne depasse le reste du.
     */
    public function payerFactureMensualites(int|string $inscriptionId, array $data, User $authUser): FactureMensualite
    {
        return DB::transaction(function () use ($inscriptionId, $data, $authUser) {
            $inscription = $this->repository->findInscriptionAvecMensualitesForUpdate($inscriptionId);

            if ($inscription->estAnnulee()) {
                abort(422, "Impossible d'encaisser les mensualités d'une inscription annulée.");
            }

            $imputations = $data['mode_repartition'] === 'SELECTION'
                ? $this->imputationsDepuisSelection($inscription, $data['lignes'] ?? [])
                : $this->imputationsEnCascade($inscription, (int) ($data['montant'] ?? 0));

            $montantTotal = array_sum(array_column($imputations, 'montant'));

            $facture = $this->repository->createFactureMensualite([
                'inscription_id'     => $inscription->id,
                'utilisateur_id'     => $authUser->id,
                'numero_facture'     => $this->repository->nextNumeroFacture($inscription->annee_scolaire_id),
                'montant_total'      => $montantTotal,
                'mode_paiement'      => $data['mode_paiement'],
                'numero_transaction' => $data['numero_transaction'] ?? null,
                'date_paiement'      => $data['date_paiement'] ?? now()->toDateString(),
            ]);

            $moisTouches = [];

            foreach ($imputations as $imputation) {
                /** @var Mensualite $mensualite */
                $mensualite = $imputation['mensualite'];

                $this->repository->createPaiementMensualite([
                    'mensualite_id'         => $mensualite->id,
                    'facture_mensualite_id' => $facture->id,
                    'utilisateur_id'        => $authUser->id,
                    'numero_recu'           => $this->repository->nextNumeroRecuMensualite($inscription->annee_scolaire_id),
                    'montant'               => $imputation['montant'],
                    'mode_paiement'         => $data['mode_paiement'],
                    'numero_transaction'    => $data['numero_transaction'] ?? null,
                    'date_paiement'         => $data['date_paiement'] ?? now()->toDateString(),
                ]);

                $mensualite->load('paiements');
                $mensualite->synchroniserStatut();

                $moisTouches[] = sprintf('%02d/%d', $mensualite->mois, $mensualite->annee);
            }

            $facture = $this->repository->findFactureMensualiteById($facture->id);

            $this->activityLog->log(
                user:        $authUser,
                action:      'paid',
                module:      'finance-tresorier',
                description: sprintf(
                    "Facture %s de %d sur l'inscription « %s » — %d mois réglé(s) : %s",
                    $facture->numero_facture,
                    $montantTotal,
                    $inscription->numero_inscription,
                    count($moisTouches),
                    implode(', ', $moisTouches),
                ),
                subject:     $facture,
                newValues:   $facture->toArray(),
            );

            // Le reste porte sur l'annee entiere : une facture multi-mois
            // couvre plusieurs echeances, et la famille veut savoir ou elle en
            // est globalement, pas mois par mois.
            $inscription->load('mensualites.paiements');
            $resteAnnuel = (int) $inscription->mensualites->sum(fn ($m) => $m->reste);

            $this->notifierFamille($inscription->eleve, [
                'libelle'  => sprintf(
                    '%d mensualité%s (%s)',
                    count($moisTouches),
                    count($moisTouches) > 1 ? 's' : '',
                    implode(', ', $moisTouches),
                ),
                'montant'  => $montantTotal,
                'reste'    => $resteAnnuel,
                'estSolde' => $resteAnnuel <= 0,
                'numero'   => $facture->numero_facture,
                'type'     => PieceJointeEnum::FACTURE_MENSUALITES,
                'pieceId'  => $facture->id,
            ]);

            return $facture;
        });
    }

    /**
     * Repartit un montant global sur les mois non soldes, du plus ancien au
     * plus recent. Chaque mois absorbe au plus son reste ; le surplus glisse
     * sur le mois suivant, qui devient une avance partielle.
     *
     * @return list<array{mensualite: Mensualite, montant: int}>
     */
    private function imputationsEnCascade(Inscription $inscription, int $montant): array
    {
        if ($montant <= 0) {
            abort(422, "Le montant versé doit être strictement supérieur à 0.");
        }

        $aSolder = $inscription->mensualites
            ->filter(fn (Mensualite $m) => $m->reste > 0)
            ->sortBy(fn (Mensualite $m) => sprintf('%04d%02d', $m->annee, $m->mois))
            ->values();

        $resteTotal = (int) $aSolder->sum(fn (Mensualite $m) => $m->reste);

        if ($resteTotal <= 0) {
            abort(422, "Toutes les mensualités de cet élève sont déjà soldées.");
        }

        if ($montant > $resteTotal) {
            abort(422, "Le montant versé ({$montant}) dépasse le reste total à payer ({$resteTotal}).");
        }

        $restant     = $montant;
        $imputations = [];

        foreach ($aSolder as $mensualite) {
            if ($restant <= 0) {
                break;
            }

            $aImputer = min($restant, $mensualite->reste);

            $imputations[] = ['mensualite' => $mensualite, 'montant' => $aImputer];
            $restant -= $aImputer;
        }

        return $imputations;
    }

    /**
     * Valide les mois coches par le tresorier : chacun doit appartenir a cette
     * inscription, ne pas etre deja solde, et ne pas recevoir plus que son
     * reste du. On ordonne du plus ancien au plus recent pour que la facture
     * se lise dans l'ordre chronologique.
     *
     * @param  list<array{mensualite_id: int|string, montant: int|string}>  $lignes
     * @return list<array{mensualite: Mensualite, montant: int}>
     */
    private function imputationsDepuisSelection(Inscription $inscription, array $lignes): array
    {
        if (empty($lignes)) {
            abort(422, "Sélectionnez au moins un mois à régler.");
        }

        $parId       = $inscription->mensualites->keyBy('id');
        $imputations = [];

        foreach ($lignes as $ligne) {
            $mensualite = $parId->get((int) $ligne['mensualite_id']);

            if (! $mensualite) {
                abort(422, "Un des mois sélectionnés n'appartient pas à cet élève.");
            }

            $montant = (int) $ligne['montant'];

            if ($montant <= 0) {
                abort(422, "Le montant imputé sur un mois doit être strictement supérieur à 0.");
            }

            $reste = $mensualite->reste;

            if ($reste <= 0) {
                abort(422, sprintf(
                    "La mensualité %02d/%d est déjà intégralement payée.",
                    $mensualite->mois,
                    $mensualite->annee,
                ));
            }

            if ($montant > $reste) {
                abort(422, sprintf(
                    "Le montant imputé sur %02d/%d (%d) dépasse le reste à payer (%d).",
                    $mensualite->mois,
                    $mensualite->annee,
                    $montant,
                    $reste,
                ));
            }

            $imputations[] = ['mensualite' => $mensualite, 'montant' => $montant];
        }

        usort(
            $imputations,
            fn (array $a, array $b) => sprintf('%04d%02d', $a['mensualite']->annee, $a['mensualite']->mois)
                <=> sprintf('%04d%02d', $b['mensualite']->annee, $b['mensualite']->mois),
        );

        return $imputations;
    }

    public function listFacturesMensualite(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginateFacturesMensualite($perPage, $search);
    }

    public function findFactureMensualite(int|string $id): FactureMensualite
    {
        return $this->repository->findFactureMensualiteById($id);
    }

    /**
     * Justificatif unique d'une facture multi-mois : un tableau des mois regles
     * plutot qu'un document par mois. Recu si la facture solde tous les mois
     * qu'elle couvre, decharge si l'un d'eux reste partiellement du.
     */
    public function dataForFactureMensualite(int|string $factureId): array
    {
        $facture     = $this->repository->findFactureMensualiteById($factureId);
        $inscription = $facture->inscription;

        $moisFr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        // Une ligne par mois regle, dans l'ordre chronologique : c'est le detail
        // que le parent doit retrouver sur son justificatif.
        $lignes = $facture->lignes
            ->sortBy(fn (PaiementMensualite $p) => sprintf(
                '%04d%02d',
                $p->mensualite?->annee ?? 0,
                $p->mensualite?->mois ?? 0,
            ))
            ->map(fn (PaiementMensualite $p) => [
                'libelle'    => $p->mensualite
                    ? sprintf('%s %d', $moisFr[$p->mensualite->mois] ?? $p->mensualite->mois, $p->mensualite->annee)
                    : 'Mensualité',
                'montant_du' => (int) ($p->mensualite?->montant_mensualite ?? 0),
                'montant'    => (int) $p->montant,
                'reste'      => (int) ($p->mensualite?->reste ?? 0),
                'solde'      => (int) ($p->mensualite?->reste ?? 0) <= 0,
                'numero_recu' => $p->numero_recu,
            ])
            ->values()
            ->all();

        // Situation annuelle : ce que le parent veut voir apres avoir paye.
        $mensualites  = $inscription?->mensualites ?? collect();
        $totalAnnuel  = (int) $mensualites->sum('montant_mensualite');
        $totalPaye    = (int) $mensualites->sum(fn (Mensualite $m) => $m->total_paye);
        $resteAnnuel  = max(0, $totalAnnuel - $totalPaye);

        $estSolde = collect($lignes)->every(fn (array $l) => $l['solde']);

        return [
            'facture'      => $facture,
            'lignes'       => $lignes,
            'inscription'  => $inscription,
            'eleve'        => $inscription?->eleve,
            'classe'       => $inscription?->classe,
            'annee'        => $inscription?->anneeScolaire,
            'montantDu'    => $totalAnnuel,
            'totalPaye'    => $totalPaye,
            'reste'        => $resteAnnuel,
            'estSolde'     => $estSolde,
        ];
    }

    public function listMensualitesAEncaisser(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginateMensualitesAEncaisser($perPage, $search);
    }

    /**
     * Regle metier : une mensualite peut etre reglee en plusieurs tranches. Un
     * versement doit etre strictement positif et ne jamais depasser le reste a
     * payer. Le statut derive des paiements reels devient PARTIEL ou PAYE.
     */
    public function payerMensualite(int|string $mensualiteId, array $data, User $authUser): Mensualite
    {
        return DB::transaction(function () use ($mensualiteId, $data, $authUser) {
            $mensualite = $this->repository->findMensualiteForUpdate($mensualiteId);
            $oldValues  = $mensualite->toArray();

            if ($mensualite->inscription?->estAnnulee()) {
                abort(422, "Impossible d'encaisser une mensualité d'une inscription annulée.");
            }

            $montant = (int) $data['montant'];

            if ($montant <= 0) {
                abort(422, "Le montant versé doit être strictement supérieur à 0.");
            }

            $reste = $mensualite->reste;

            if ($reste <= 0) {
                abort(422, "Cette mensualité est déjà intégralement payée.");
            }

            if ($montant > $reste) {
                abort(422, "Le montant versé ({$montant}) dépasse le reste à payer ({$reste}).");
            }

            $anneeScolaireId = $mensualite->inscription->annee_scolaire_id;

            $paiement = $this->repository->createPaiementMensualite([
                'mensualite_id'      => $mensualite->id,
                'utilisateur_id'     => $authUser->id,
                'numero_recu'        => $this->repository->nextNumeroRecuMensualite($anneeScolaireId),
                'montant'            => $montant,
                'mode_paiement'      => $data['mode_paiement'],
                'numero_transaction' => $data['numero_transaction'] ?? null,
                'date_paiement'      => $data['date_paiement'] ?? now()->toDateString(),
            ]);

            // Recharger les paiements pour que le statut derive voie le
            // versement qu'on vient d'inserer, puis le persister.
            $mensualite->load('paiements');
            $mensualite->synchroniserStatut();

            $mensualite = $this->repository->findMensualiteForUpdate($mensualite->id);

            // Expose le versement qui vient d'etre cree pour que le tresorier
            // puisse imprimer son justificatif dans la foulee, sans le rechercher.
            $mensualite->setAttribute('paiement_cree', $paiement);

            $this->activityLog->log(
                user:        $authUser,
                action:      'paid',
                module:      'finance-tresorier',
                description: sprintf(
                    "Versement de %d sur la mensualité %02d/%d de l'inscription « %s » (reçu %s) — statut : %s, reste : %d",
                    $montant,
                    $mensualite->mois,
                    $mensualite->annee,
                    $mensualite->inscription?->numero_inscription,
                    $paiement->numero_recu,
                    $mensualite->statut->value,
                    $mensualite->reste,
                ),
                subject:     $mensualite,
                oldValues:   $oldValues,
                newValues:   $mensualite->toArray(),
            );

            $this->notifierFamille($mensualite->inscription?->eleve, [
                'libelle'  => $this->libelleMensualite($mensualite),
                'montant'  => $montant,
                'reste'    => (int) $mensualite->reste,
                'estSolde' => (int) $mensualite->reste <= 0,
                'numero'   => $paiement->numero_recu,
                'type'     => PieceJointeEnum::RECU_MENSUALITE,
                'pieceId'  => $paiement->id,
            ]);

            return $mensualite;
        });
    }

    public function listPaiementsMensualite(int $perPage, string $search): LengthAwarePaginator
    {
        return $this->repository->paginatePaiementsMensualite($perPage, $search);
    }

    public function findPaiementMensualite(int|string $id): PaiementMensualite
    {
        return $this->repository->findPaiementMensualiteById($id);
    }

    /**
     * Justificatif d'un versement sur mensualite. Le document emis depend du
     * solde du mois concerne apres ce versement : recu si le mois est solde,
     * decharge tant qu'il reste un solde exigible sur ce mois.
     */
    public function dataForRecuMensualite(int|string $paiementId): array
    {
        $paiement    = $this->repository->findPaiementMensualiteById($paiementId);
        $mensualite  = $paiement->mensualite;
        $inscription = $mensualite?->inscription;

        $moisFr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        $montantDu = (int) ($mensualite?->montant_mensualite ?? 0);
        $totalPaye = (int) ($mensualite?->total_paye ?? 0);
        $reste     = (int) ($mensualite?->reste ?? 0);

        $libelle = $mensualite
            ? sprintf('Mensualité de %s %d', $moisFr[$mensualite->mois] ?? $mensualite->mois, $mensualite->annee)
            : 'Mensualité';

        return [
            'paiement'     => $paiement,
            'inscription'  => $inscription,
            'eleve'        => $inscription?->eleve,
            'classe'       => $inscription?->classe,
            'annee'        => $inscription?->anneeScolaire,
            'libelleObjet' => $libelle,
            'montantDu'    => $montantDu,
            'totalPaye'    => $totalPaye,
            'reste'        => $reste,
            'estSolde'     => $reste <= 0,
        ];
    }
}
