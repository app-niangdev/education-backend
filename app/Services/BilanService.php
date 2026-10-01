<?php

namespace App\Services;

use App\Enums\CategorieDepenseEnum;
use App\Enums\ModePaiementEnum;
use App\Enums\ModeRemunerationEnum;
use App\Enums\StatutContratEnum;
use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use App\Interfaces\BilanServiceInterface;
use App\Models\AnneeScolaire;
use App\Models\Contrat;
use App\Models\Depense;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Le bilan financier de l'etablissement pour une annee scolaire.
 *
 * Deux natures de chiffres cohabitent ici, et les confondre fausserait la
 * lecture :
 *
 *  - les FLUX (encaissements, depenses) appartiennent a une date, donc a un
 *    mois : ils se filtrent. Le resultat, qui en decoule, se filtre aussi.
 *  - les CREANCES (ce que les eleves doivent encore) sont un etat cumule a
 *    l'instant present. Les borner au mois n'aurait pas de sens : un impaye
 *    d'octobre reste du en janvier.
 *
 * Le resultat retenu est un resultat de TRESORERIE — encaisse moins depense —
 * et non un resultat comptable : il dit ce qui est reellement entre et sorti
 * de la caisse. Le reste a recouvrer figure a part, comme creance.
 */
class BilanService implements BilanServiceInterface
{
    public function bilan(?AnneeScolaire $annee = null, ?int $mois = null, ?int $anneeCivile = null): array
    {
        $annee ??= AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        if (!$annee) {
            return $this->bilanVide();
        }

        // Un mois sans son millesime est ambigu : une annee scolaire couvre
        // deux annees civiles, « juin » pourrait designer l'une ou l'autre.
        // On ignore alors le filtre plutot que de deviner.
        $moisValide = $mois !== null && $mois >= 1 && $mois <= 12 && $anneeCivile !== null;
        $mois       = $moisValide ? $mois : null;
        $anneeCivile = $moisValide ? $anneeCivile : null;

        $encaissements = $this->encaissements($annee, $mois, $anneeCivile);
        $depenses      = $this->depenses($annee, $mois, $anneeCivile);

        return [
            'annee_scolaire'      => ['id' => $annee->id, 'nom' => $annee->nom],
            'periode'             => $this->periode($annee, $mois, $anneeCivile),
            'mois_disponibles'    => $this->moisDisponibles($annee),
            'resultat'            => $this->resultat($encaissements, $depenses),
            'encaissements'       => $encaissements,
            'depenses'            => $depenses,
            'masse_salariale'     => $this->masseSalariale($depenses),
            'creances'            => $this->creances($annee),
            'evolution_mensuelle' => $this->evolutionMensuelle($annee),
        ];
    }

    // ─── Perimetre ────────────────────────────────────────────────────────────

    /** Ce que le bilan affiche en en-tete : sur quoi portent les chiffres. */
    private function periode(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): array
    {
        if ($mois === null) {
            return [
                'type'    => 'annee',
                'mois'    => null,
                'annee'   => null,
                'libelle' => 'Toute l\'année scolaire ' . $annee->nom,
            ];
        }

        return [
            'type'    => 'mois',
            'mois'    => $mois,
            'annee'   => $anneeCivile,
            'libelle' => $this->libelleMois($mois) . ' ' . $anneeCivile,
        ];
    }

    /**
     * Les mois que le filtre propose : tous ceux de l'annee scolaire, y compris
     * ceux sans mouvement — leur absence de la liste laisserait croire que le
     * mois n'existe pas, alors qu'il est simplement vide.
     *
     * La plage est etendue aux mois qui portent effectivement un mouvement,
     * meme hors du calendrier scolaire. Les inscriptions s'encaissent souvent
     * avant la rentree : ces montants entrent dans les totaux annuels, et les
     * omettre de la ventilation mensuelle ferait un bilan dont la somme des
     * mois ne retombe pas sur son propre total.
     */
    private function moisDisponibles(AnneeScolaire $annee): array
    {
        $debut = CarbonImmutable::parse($annee->date_debut)->startOfMonth();
        $fin   = CarbonImmutable::parse($annee->date_fin)->startOfMonth();

        foreach ($this->bornesDesMouvements($annee) as $borne) {
            $debut = $borne->lessThan($debut) ? $borne : $debut;
            $fin   = $borne->greaterThan($fin) ? $borne : $fin;
        }

        $mois = [];

        for ($courant = $debut; $courant <= $fin; $courant = $courant->addMonth()) {
            $numero      = (int) $courant->format('n');
            $anneeCivile = (int) $courant->format('Y');

            $mois[] = [
                'mois'    => $numero,
                'annee'   => $anneeCivile,
                'libelle' => $this->libelleMois($numero) . ' ' . $anneeCivile,
                // Distingue les mois hors calendrier scolaire, que le front
                // signale plutot que de les presenter comme des mois ordinaires.
                'hors_calendrier' => $courant->lessThan(CarbonImmutable::parse($annee->date_debut)->startOfMonth())
                    || $courant->greaterThan(CarbonImmutable::parse($annee->date_fin)->startOfMonth()),
            ];
        }

        return $mois;
    }

    /**
     * Le premier et le dernier mois porteurs d'un mouvement, toutes sources
     * confondues. Sert a etendre la plage mensuelle au-dela du calendrier
     * scolaire quand les faits l'exigent.
     *
     * @return array<int, CarbonImmutable>
     */
    private function bornesDesMouvements(AnneeScolaire $annee): array
    {
        $sources = [
            [$this->paiementsInscriptionScopes($annee, null, null), 'date_paiement'],
            [$this->paiementsMensualiteScopes($annee, null, null),  'date_paiement'],
            [$this->depensesScopees($annee, null, null),            'date_depense'],
        ];

        $bornes = [];

        foreach ($sources as [$requete, $colonne]) {
            $extremes = (clone $requete)
                ->selectRaw("MIN({$colonne}) as premier, MAX({$colonne}) as dernier")
                ->first();

            foreach ([$extremes?->premier, $extremes?->dernier] as $date) {
                if ($date) {
                    $bornes[] = CarbonImmutable::parse($date)->startOfMonth();
                }
            }
        }

        return $bornes;
    }

    // ─── Flux : encaissements ─────────────────────────────────────────────────

    /**
     * Ce qui est entre en caisse sur le perimetre : inscriptions et mensualites
     * cumulees, avec le detail par source et par mode de paiement.
     */
    private function encaissements(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): array
    {
        $inscriptions = $this->paiementsInscriptionScopes($annee, $mois, $anneeCivile);
        $mensualites  = $this->paiementsMensualiteScopes($annee, $mois, $anneeCivile);

        $montantInscriptions = (int) (clone $inscriptions)->sum('montant');
        $nombreInscriptions  = (clone $inscriptions)->count();

        $montantMensualites = (int) (clone $mensualites)->sum('montant');
        $nombreMensualites  = (clone $mensualites)->count();

        return [
            'total'  => $montantInscriptions + $montantMensualites,
            'nombre' => $nombreInscriptions + $nombreMensualites,
            'par_source' => [
                'inscriptions' => ['montant' => $montantInscriptions, 'nombre' => $nombreInscriptions],
                'mensualites'  => ['montant' => $montantMensualites,  'nombre' => $nombreMensualites],
            ],
            'par_mode_paiement' => $this->encaissementsParMode($inscriptions, $mensualites),
        ];
    }

    /**
     * Encaissements ventiles par mode de paiement, les deux sources confondues.
     * Tous les modes sont listes, meme a zero : la repartition doit se lire sur
     * un referentiel stable d'un mois a l'autre.
     */
    private function encaissementsParMode(Builder $inscriptions, Builder $mensualites): array
    {
        $parModeInscription = (clone $inscriptions)
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        $parModeMensualite = (clone $mensualites)
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        $resultat = [];

        foreach (ModePaiementEnum::cases() as $mode) {
            $resultat[] = [
                'mode'    => $mode->value,
                'libelle' => $mode->libelle(),
                'montant' => (int) ($parModeInscription[$mode->value] ?? 0)
                           + (int) ($parModeMensualite[$mode->value] ?? 0),
            ];
        }

        return $resultat;
    }

    // ─── Flux : depenses ──────────────────────────────────────────────────────

    /**
     * Ce qui est sorti de caisse sur le perimetre, ventile par poste. Le poste
     * SALAIRES est isole en plus de la ventilation : c'est le premier poste
     * d'un etablissement scolaire, et le bilan le confronte plus bas a la
     * masse salariale contractuelle.
     */
    private function depenses(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): array
    {
        $requete = $this->depensesScopees($annee, $mois, $anneeCivile);

        $parCategorie = (clone $requete)
            ->select('categorie', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nombre'))
            ->groupBy('categorie')
            ->pluck('total', 'categorie');

        $nombreParCategorie = (clone $requete)
            ->select('categorie', DB::raw('COUNT(*) as nombre'))
            ->groupBy('categorie')
            ->pluck('nombre', 'categorie');

        $ventilation = [];

        foreach (CategorieDepenseEnum::cases() as $categorie) {
            $ventilation[] = [
                'categorie' => $categorie->value,
                'libelle'   => $categorie->libelle(),
                'montant'   => (int) ($parCategorie[$categorie->value] ?? 0),
                'nombre'    => (int) ($nombreParCategorie[$categorie->value] ?? 0),
            ];
        }

        return [
            'total'         => (int) $parCategorie->sum(),
            'nombre'        => (int) $nombreParCategorie->sum(),
            'salaires'      => (int) ($parCategorie[CategorieDepenseEnum::SALAIRES->value] ?? 0),
            'par_categorie' => $ventilation,
        ];
    }

    // ─── Resultat ─────────────────────────────────────────────────────────────

    /**
     * Le resultat de tresorerie du perimetre : ce qui est entre moins ce qui
     * est sorti. La marge rapporte ce resultat aux encaissements — elle dit
     * quelle part de chaque franc encaisse reste a l'etablissement.
     */
    private function resultat(array $encaissements, array $depenses): array
    {
        $entrees = $encaissements['total'];
        $sorties = $depenses['total'];
        $solde   = $entrees - $sorties;

        return [
            'encaisse'   => $entrees,
            'depense'    => $sorties,
            'benefice'   => $solde,
            'excedent'   => $solde >= 0,
            // Sans encaissement, il n'y a pas de marge a calculer : 0 plutot
            // qu'une division par zero, et le front n'a pas a s'en soucier.
            'marge'      => $entrees > 0 ? round(($solde / $entrees) * 100, 1) : 0.0,
            'taux_charge' => $entrees > 0 ? round(($sorties / $entrees) * 100, 1) : 0.0,
        ];
    }

    // ─── Masse salariale ──────────────────────────────────────────────────────

    /**
     * Confronte ce qui a ete reellement paye en salaires a ce que les contrats
     * engagent chaque mois.
     *
     * Seuls les contrats a remuneration MENSUELLE entrent dans l'engagement :
     * un taux horaire ne devient un montant qu'une fois les heures faites, et
     * l'application ne les compte pas. Ces contrats sont denombres a part pour
     * que l'ecart affiche ne soit pas lu comme une anomalie.
     */
    private function masseSalariale(array $depenses): array
    {
        $contratsEnCours = Contrat::query()
            ->whereIn('statut', [StatutContratEnum::ACTIF->value, StatutContratEnum::SUSPENDU->value])
            ->get(['salaire_base', 'mode_remuneration']);

        $mensuels = $contratsEnCours->where('mode_remuneration', ModeRemunerationEnum::MENSUEL);
        $horaires = $contratsEnCours->where('mode_remuneration', ModeRemunerationEnum::HORAIRE);

        $engagementMensuel = (int) $mensuels->sum('salaire_base');
        $decaisse          = $depenses['salaires'];

        return [
            'decaisse'            => $decaisse,
            'engagement_mensuel'  => $engagementMensuel,
            'ecart'               => $decaisse - $engagementMensuel,
            'contrats_actifs'     => $contratsEnCours->count(),
            'contrats_mensuels'   => $mensuels->count(),
            // Les contrats horaires echappent a l'engagement mensuel : on le
            // signale, sans quoi l'ecart paraitrait inexplique.
            'contrats_horaires'   => $horaires->count(),
            'part_des_depenses'   => $depenses['total'] > 0
                ? round(($decaisse / $depenses['total']) * 100, 1)
                : 0.0,
        ];
    }

    // ─── Creances ─────────────────────────────────────────────────────────────

    /**
     * Ce que les eleves doivent encore, cumule sur l'annee et jamais borne au
     * mois : un impaye ne disparait pas quand on change de mois d'analyse.
     *
     * Le du fait foi cote serveur (montant_inscription, montant_mensualite) ;
     * l'encaisse vient des paiements reellement enregistres.
     */
    private function creances(AnneeScolaire $annee): array
    {
        $inscriptionsActives = fn ($q) => $q
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE);

        $inscriptionDu = (int) Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->sum('montant_inscription');

        $inscriptionEncaisse = (int) PaiementInscription::whereHas('inscription', $inscriptionsActives)
            ->sum('montant');

        $mensualiteDu = (int) Mensualite::whereHas('inscription', $inscriptionsActives)
            ->sum('montant_mensualite');

        $mensualiteEncaisse = (int) PaiementMensualite::whereHas(
            'mensualite.inscription',
            $inscriptionsActives
        )->sum('montant');

        $totalDu       = $inscriptionDu + $mensualiteDu;
        $totalEncaisse = $inscriptionEncaisse + $mensualiteEncaisse;
        $totalReste    = max(0, $totalDu - $totalEncaisse);

        return [
            'inscriptions' => [
                'du'       => $inscriptionDu,
                'encaisse' => $inscriptionEncaisse,
                'reste'    => max(0, $inscriptionDu - $inscriptionEncaisse),
            ],
            'mensualites' => [
                'du'       => $mensualiteDu,
                'encaisse' => $mensualiteEncaisse,
                'reste'    => max(0, $mensualiteDu - $mensualiteEncaisse),
            ],
            'total' => [
                'du'       => $totalDu,
                'encaisse' => $totalEncaisse,
                'reste'    => $totalReste,
            ],
            'taux_recouvrement' => $totalDu > 0
                ? round(($totalEncaisse / $totalDu) * 100, 1)
                : 0.0,
            'eleves_debiteurs' => $this->elevesDebiteurs($annee),
        ];
    }

    /**
     * Combien d'eleves restent debiteurs, et sur quoi. Un meme eleve peut
     * l'etre des deux cotes : les deux comptes ne s'additionnent donc pas, et
     * `total` denombre les eleves distincts concernes.
     */
    private function elevesDebiteurs(AnneeScolaire $annee): array
    {
        $nonSolde = [StatutPaiementEnum::NON_PAYE, StatutPaiementEnum::PARTIEL];

        $surInscription = Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->whereIn('statut_paiement', $nonSolde);

        $surMensualite = Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->whereHas('mensualites', fn ($q) => $q->whereIn('statut', $nonSolde));

        return [
            'inscriptions' => (clone $surInscription)->count(),
            'mensualites'  => (clone $surMensualite)->count(),
            'total'        => Inscription::where('annee_scolaire_id', $annee->id)
                ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
                ->where(function ($q) use ($nonSolde) {
                    $q->whereIn('statut_paiement', $nonSolde)
                      ->orWhereHas('mensualites', fn ($sub) => $sub->whereIn('statut', $nonSolde));
                })
                ->distinct('eleve_id')
                ->count('eleve_id'),
        ];
    }

    // ─── Evolution ────────────────────────────────────────────────────────────

    /**
     * La courbe de l'annee : pour chaque mois, ce qui est entre, ce qui est
     * sorti et le solde. Toujours calculee sur l'annee entiere, meme quand un
     * mois est selectionne — c'est ce qui donne au mois filtre son relief.
     *
     * Trois agregats groupes suffisent : une requete par mois en ferait
     * plusieurs dizaines pour le meme resultat.
     */
    private function evolutionMensuelle(AnneeScolaire $annee): array
    {
        $inscriptionsParMois = $this->sommeParMois(
            $this->paiementsInscriptionScopes($annee, null, null),
            'date_paiement'
        );

        $mensualitesParMois = $this->sommeParMois(
            $this->paiementsMensualiteScopes($annee, null, null),
            'date_paiement'
        );

        $depensesParMois = $this->sommeParMois(
            $this->depensesScopees($annee, null, null),
            'date_depense'
        );

        $lignes = [];

        foreach ($this->moisDisponibles($annee) as $mois) {
            $cle = "{$mois['annee']}-{$mois['mois']}";

            $encaisse = (int) ($inscriptionsParMois[$cle] ?? 0)
                      + (int) ($mensualitesParMois[$cle] ?? 0);
            $depense  = (int) ($depensesParMois[$cle] ?? 0);

            $lignes[] = [
                'mois'     => $mois['mois'],
                'annee'    => $mois['annee'],
                'libelle'  => $mois['libelle'],
                // Libelle court pour les axes de graphique, ou la place manque.
                'court'    => mb_substr($this->libelleMois($mois['mois']), 0, 3),
                'hors_calendrier' => $mois['hors_calendrier'],
                'encaisse' => $encaisse,
                'depense'  => $depense,
                'solde'    => $encaisse - $depense,
            ];
        }

        return $lignes;
    }

    /**
     * Somme mensuelle d'une requete, indexee « annee-mois ».
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function sommeParMois(Builder $requete, string $colonneDate)
    {
        return $requete
            ->select(
                DB::raw("EXTRACT(MONTH FROM {$colonneDate})::int as mois_civil"),
                DB::raw("EXTRACT(YEAR FROM {$colonneDate})::int as annee_civile"),
                DB::raw('SUM(montant) as total'),
            )
            ->groupBy('mois_civil', 'annee_civile')
            ->get()
            ->mapWithKeys(fn ($ligne) => [
                "{$ligne->annee_civile}-{$ligne->mois_civil}" => (int) $ligne->total,
            ]);
    }

    // ─── Requetes de base ─────────────────────────────────────────────────────

    /**
     * Les paiements d'inscription de l'annee, eventuellement du seul mois vise.
     * Les inscriptions annulees sont exclues : leur encaissement, s'il a eu
     * lieu, ne represente plus une recette acquise.
     */
    private function paiementsInscriptionScopes(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): Builder
    {
        $requete = PaiementInscription::query()
            ->whereHas('inscription', fn ($q) => $q
                ->where('annee_scolaire_id', $annee->id)
                ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE));

        return $this->bornerAuMois($requete, 'date_paiement', $mois, $anneeCivile);
    }

    /** Les paiements de mensualite de l'annee, eventuellement du mois vise. */
    private function paiementsMensualiteScopes(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): Builder
    {
        $requete = PaiementMensualite::query()
            ->whereHas('mensualite.inscription', fn ($q) => $q
                ->where('annee_scolaire_id', $annee->id)
                ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE));

        return $this->bornerAuMois($requete, 'date_paiement', $mois, $anneeCivile);
    }

    /**
     * Les depenses de l'annee, eventuellement du seul mois vise.
     *
     * Seules les depenses VALIDEES entrent dans le bilan : une depense en
     * attente n'a pas encore ete engagee par le manager, et une refusee ne le
     * sera jamais. Les compter fausserait le resultat de tresorerie en y
     * portant des sorties qui peuvent ne jamais avoir lieu.
     */
    private function depensesScopees(AnneeScolaire $annee, ?int $mois, ?int $anneeCivile): Builder
    {
        $requete = Depense::query()
            ->comptabilisees()
            ->where('annee_scolaire_id', $annee->id);

        return $this->bornerAuMois($requete, 'date_depense', $mois, $anneeCivile);
    }

    private function bornerAuMois(Builder $requete, string $colonneDate, ?int $mois, ?int $anneeCivile): Builder
    {
        if ($mois === null || $anneeCivile === null) {
            return $requete;
        }

        return $requete
            ->whereMonth($colonneDate, $mois)
            ->whereYear($colonneDate, $anneeCivile);
    }

    // ─── Divers ───────────────────────────────────────────────────────────────

    private function libelleMois(int $mois): string
    {
        return [
            1 => 'Janvier',   2 => 'Février',  3 => 'Mars',      4 => 'Avril',
            5 => 'Mai',       6 => 'Juin',     7 => 'Juillet',   8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ][$mois] ?? '';
    }

    /**
     * Sans annee scolaire, la structure reste identique : le front affiche un
     * bilan a zero plutot que de gerer un cas particulier.
     */
    private function bilanVide(): array
    {
        return [
            'annee_scolaire'   => null,
            'periode'          => ['type' => 'annee', 'mois' => null, 'annee' => null, 'libelle' => '—'],
            'mois_disponibles' => [],
            'resultat'         => [
                'encaisse' => 0, 'depense' => 0, 'benefice' => 0,
                'excedent' => true, 'marge' => 0.0, 'taux_charge' => 0.0,
            ],
            'encaissements'    => [
                'total'  => 0,
                'nombre' => 0,
                'par_source' => [
                    'inscriptions' => ['montant' => 0, 'nombre' => 0],
                    'mensualites'  => ['montant' => 0, 'nombre' => 0],
                ],
                'par_mode_paiement' => array_map(
                    fn (ModePaiementEnum $m) => ['mode' => $m->value, 'libelle' => $m->libelle(), 'montant' => 0],
                    ModePaiementEnum::cases(),
                ),
            ],
            'depenses' => [
                'total'    => 0,
                'nombre'   => 0,
                'salaires' => 0,
                'par_categorie' => array_map(
                    fn (CategorieDepenseEnum $c) => [
                        'categorie' => $c->value, 'libelle' => $c->libelle(),
                        'montant' => 0, 'nombre' => 0,
                    ],
                    CategorieDepenseEnum::cases(),
                ),
            ],
            'masse_salariale' => [
                'decaisse' => 0, 'engagement_mensuel' => 0, 'ecart' => 0,
                'contrats_actifs' => 0, 'contrats_mensuels' => 0,
                'contrats_horaires' => 0, 'part_des_depenses' => 0.0,
            ],
            'creances' => [
                'inscriptions'      => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'mensualites'       => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'total'             => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'taux_recouvrement' => 0.0,
                'eleves_debiteurs'  => ['inscriptions' => 0, 'mensualites' => 0, 'total' => 0],
            ],
            'evolution_mensuelle' => [],
        ];
    }
}
