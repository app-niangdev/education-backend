<?php

namespace App\Services;

use App\Enums\ModePaiementEnum;
use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use App\Enums\TypeEvaluationEnum;
use App\Interfaces\StatistiqueServiceInterface;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use App\Models\Surveillant;
use App\Models\Tresorier;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Agrege les indicateurs du tableau de bord manager. Les effectifs de personnel
 * sont globaux ; les eleves, inscriptions et finances sont bornes a l'annee
 * scolaire consideree (celle en cours par defaut).
 */
class StatistiqueService implements StatistiqueServiceInterface
{
    public function dashboard(?AnneeScolaire $annee = null): array
    {
        $annee ??= AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        // Sans annee scolaire, on renvoie une structure vide mais coherente.
        if (!$annee) {
            return $this->dashboardVide();
        }

        return [
            'annee_scolaire'          => [
                'id'  => $annee->id,
                'nom' => $annee->nom,
            ],
            'effectifs'               => $this->effectifs($annee),
            'eleves_par_sexe'         => $this->elevesParSexe($annee),
            'eleves_par_niveau'       => $this->elevesParNiveau($annee),
            'inscriptions_par_statut' => $this->inscriptionsParStatut($annee),
            'finances'                => $this->finances($annee),
        ];
    }

    public function dashboardTresorier(?AnneeScolaire $annee = null): array
    {
        $annee ??= AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        if (!$annee) {
            return $this->dashboardTresorierVide();
        }

        return [
            'annee_scolaire'         => [
                'id'  => $annee->id,
                'nom' => $annee->nom,
            ],
            'finances'               => $this->finances($annee),
            'encaissements'          => $this->encaissements($annee),
            'par_mode_paiement'      => $this->parModePaiement($annee),
            'mensualites_par_statut' => $this->mensualitesParStatut($annee),
            'a_encaisser'            => $this->aEncaisser($annee),
        ];
    }

    public function dashboardEnseignant(User $enseignantUser, ?AnneeScolaire $annee = null): array
    {
        $annee ??= AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderByDesc('date_debut')->first();

        $enseignantId = $enseignantUser->enseignant?->id;

        // Sans profil enseignant ou sans annee, on renvoie une vue vide.
        if (!$enseignantId || !$annee) {
            return $this->dashboardEnseignantVide($annee);
        }

        // Affectations de l'enseignant (classe x matiere) de l'annee en cours.
        $affectations = Affectation::query()
            ->where('enseignant_id', $enseignantId)
            ->whereHas('classeMatiere.classe', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->with(['classeMatiere.classe', 'classeMatiere.matiere'])
            ->get();

        return [
            'annee_scolaire'       => [
                'id'  => $annee->id,
                'nom' => $annee->nom,
            ],
            'effectifs'            => $this->effectifsEnseignant($affectations, $annee),
            'evaluations_par_type' => $this->evaluationsParType($affectations),
            'saisie_notes'         => $this->saisieNotes($affectations, $annee),
            'mes_cours'            => $this->mesCours($affectations, $annee),
        ];
    }

    /** Comptages d'effectifs (eleves scopes a l'annee, personnel global). */
    private function effectifs(AnneeScolaire $annee): array
    {
        return [
            'eleves_inscrits' => Inscription::where('annee_scolaire_id', $annee->id)
                ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
                ->count(),
            'classes'         => Classe::where('annee_scolaire_id', $annee->id)->count(),
            'niveaux'         => Niveau::count(),
            'enseignants'     => Enseignant::count(),
            'surveillants'    => Surveillant::count(),
            'tresoriers'      => Tresorier::count(),
            'tuteurs'         => Tuteur::count(),
        ];
    }

    /** Repartition des eleves inscrits (non annules) par sexe. */
    private function elevesParSexe(AnneeScolaire $annee): array
    {
        $parSexe = Inscription::query()
            ->where('inscriptions.annee_scolaire_id', $annee->id)
            ->where('inscriptions.statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->select('eleves.sexe', DB::raw('COUNT(*) as total'))
            ->groupBy('eleves.sexe')
            ->pluck('total', 'sexe');

        return [
            'masculin' => (int) ($parSexe['M'] ?? 0),
            'feminin'  => (int) ($parSexe['F'] ?? 0),
        ];
    }

    /**
     * Effectif inscrit par niveau (via classe → niveau), ordonne par niveau.
     *
     * @return array<int, array{niveau: string, code: string, total: int}>
     */
    private function elevesParNiveau(AnneeScolaire $annee): array
    {
        return Inscription::query()
            ->where('inscriptions.annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->select('niveaux.id', 'niveaux.nom', 'niveaux.code', DB::raw('COUNT(*) as total'))
            ->groupBy('niveaux.id', 'niveaux.nom', 'niveaux.code')
            ->orderBy('niveaux.id')
            ->get()
            ->map(fn ($row) => [
                'niveau' => $row->nom,
                'code'   => $row->code,
                'total'  => (int) $row->total,
            ])
            ->all();
    }

    /** Comptage des inscriptions de l'annee par statut administratif. */
    private function inscriptionsParStatut(AnneeScolaire $annee): array
    {
        $parStatut = Inscription::where('annee_scolaire_id', $annee->id)
            ->select('statut_inscription', DB::raw('COUNT(*) as total'))
            ->groupBy('statut_inscription')
            ->pluck('total', 'statut_inscription');

        return [
            'en_attente' => (int) ($parStatut[StatutInscriptionEnum::EN_ATTENTE->value] ?? 0),
            'validee'    => (int) ($parStatut[StatutInscriptionEnum::VALIDEE->value] ?? 0),
            'annulee'    => (int) ($parStatut[StatutInscriptionEnum::ANNULEE->value] ?? 0),
            'total'      => (int) $parStatut->sum(),
        ];
    }

    /**
     * Indicateurs financiers de l'annee : montants dus, encaisses et restes,
     * pour les inscriptions et les mensualites, avec le taux de recouvrement.
     */
    private function finances(AnneeScolaire $annee): array
    {
        // Inscriptions non annulees : montant du fait foi cote serveur.
        $inscriptions = Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE);

        $inscriptionDu = (int) $inscriptions->sum('montant_inscription');

        $inscriptionEncaisse = (int) PaiementInscription::whereHas(
            'inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
                ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
        )->sum('montant');

        // Mensualites de l'annee (via inscription).
        $mensualiteDu = (int) Mensualite::whereHas(
            'inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
        )->sum('montant_mensualite');

        $mensualiteEncaisse = (int) PaiementMensualite::whereHas(
            'mensualite.inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
        )->sum('montant');

        $totalDu        = $inscriptionDu + $mensualiteDu;
        $totalEncaisse  = $inscriptionEncaisse + $mensualiteEncaisse;

        return [
            'inscriptions' => [
                'du'        => $inscriptionDu,
                'encaisse'  => $inscriptionEncaisse,
                'reste'     => max(0, $inscriptionDu - $inscriptionEncaisse),
            ],
            'mensualites'  => [
                'du'        => $mensualiteDu,
                'encaisse'  => $mensualiteEncaisse,
                'reste'     => max(0, $mensualiteDu - $mensualiteEncaisse),
            ],
            'total'        => [
                'du'        => $totalDu,
                'encaisse'  => $totalEncaisse,
                'reste'     => max(0, $totalDu - $totalEncaisse),
            ],
            // Part du montant total effectivement encaissee, en pourcentage.
            'taux_recouvrement' => $totalDu > 0
                ? round(($totalEncaisse / $totalDu) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Montants encaisses aujourd'hui, ce mois-ci et sur toute l'annee, en
     * cumulant paiements d'inscription et de mensualite. Le nombre de recus
     * est egalement fourni pour chaque periode.
     */
    private function encaissements(AnneeScolaire $annee): array
    {
        $aujourdhui = now()->toDateString();
        $debutMois  = now()->startOfMonth()->toDateString();

        $paiementsInscription = PaiementInscription::whereHas(
            'inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
        );

        $paiementsMensualite = PaiementMensualite::whereHas(
            'mensualite.inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
        );

        $sommeJour = (int) (clone $paiementsInscription)->whereDate('date_paiement', $aujourdhui)->sum('montant')
            + (int) (clone $paiementsMensualite)->whereDate('date_paiement', $aujourdhui)->sum('montant');

        $nbJour = (clone $paiementsInscription)->whereDate('date_paiement', $aujourdhui)->count()
            + (clone $paiementsMensualite)->whereDate('date_paiement', $aujourdhui)->count();

        $sommeMois = (int) (clone $paiementsInscription)->whereDate('date_paiement', '>=', $debutMois)->sum('montant')
            + (int) (clone $paiementsMensualite)->whereDate('date_paiement', '>=', $debutMois)->sum('montant');

        $nbMois = (clone $paiementsInscription)->whereDate('date_paiement', '>=', $debutMois)->count()
            + (clone $paiementsMensualite)->whereDate('date_paiement', '>=', $debutMois)->count();

        $sommeAnnee = (int) (clone $paiementsInscription)->sum('montant')
            + (int) (clone $paiementsMensualite)->sum('montant');

        $nbAnnee = (clone $paiementsInscription)->count()
            + (clone $paiementsMensualite)->count();

        return [
            'aujourdhui' => ['montant' => $sommeJour,  'nombre' => $nbJour],
            'ce_mois'    => ['montant' => $sommeMois,  'nombre' => $nbMois],
            'annee'      => ['montant' => $sommeAnnee, 'nombre' => $nbAnnee],
        ];
    }

    /**
     * Montant total encaisse par mode de paiement (inscription + mensualite),
     * sur l'annee scolaire.
     *
     * @return array<int, array{mode: string, montant: int}>
     */
    private function parModePaiement(AnneeScolaire $annee): array
    {
        $inscription = PaiementInscription::query()
            ->whereHas('inscription', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        $mensualite = PaiementMensualite::query()
            ->whereHas('mensualite.inscription', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->groupBy('mode_paiement')
            ->pluck('total', 'mode_paiement');

        $resultat = [];

        foreach (ModePaiementEnum::cases() as $mode) {
            $montant = (int) ($inscription[$mode->value] ?? 0) + (int) ($mensualite[$mode->value] ?? 0);
            $resultat[] = ['mode' => $mode->value, 'montant' => $montant];
        }

        return $resultat;
    }

    /** Nombre de mensualites de l'annee par statut de paiement. */
    private function mensualitesParStatut(AnneeScolaire $annee): array
    {
        $parStatut = Mensualite::query()
            ->whereHas('inscription', fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            'paye'     => (int) ($parStatut[StatutPaiementEnum::PAYE->value] ?? 0),
            'partiel'  => (int) ($parStatut[StatutPaiementEnum::PARTIEL->value] ?? 0),
            'non_paye' => (int) ($parStatut[StatutPaiementEnum::NON_PAYE->value] ?? 0),
            'total'    => (int) $parStatut->sum(),
        ];
    }

    /**
     * Reste a encaisser : nombre d'inscriptions non soldees et de mensualites
     * non soldees, avec le montant restant correspondant.
     */
    private function aEncaisser(AnneeScolaire $annee): array
    {
        $inscriptionsNonSoldees = Inscription::where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->whereIn('statut_paiement', [StatutPaiementEnum::NON_PAYE, StatutPaiementEnum::PARTIEL])
            ->count();

        $mensualitesNonSoldees = Mensualite::whereHas(
            'inscription',
            fn ($q) => $q->where('annee_scolaire_id', $annee->id)
        )->whereIn('statut', [StatutPaiementEnum::NON_PAYE, StatutPaiementEnum::PARTIEL])
            ->count();

        return [
            'inscriptions' => $inscriptionsNonSoldees,
            'mensualites'  => $mensualitesNonSoldees,
        ];
    }

    // ─── Enseignant ───────────────────────────────────────────────────────────

    /**
     * Effectifs de l'enseignant : nombre d'affectations, classes et matieres
     * distinctes, et total d'eleves couverts (union des classes enseignees).
     */
    private function effectifsEnseignant($affectations, AnneeScolaire $annee): array
    {
        $classeIds  = $affectations->pluck('classeMatiere.classe.id')->filter()->unique();
        $matiereIds = $affectations->pluck('classeMatiere.matiere.id')->filter()->unique();

        $eleves = Inscription::whereIn('classe_id', $classeIds)
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->distinct('eleve_id')
            ->count('eleve_id');

        return [
            'affectations' => $affectations->count(),
            'classes'      => $classeIds->count(),
            'matieres'     => $matiereIds->count(),
            'eleves'       => $eleves,
        ];
    }

    /** Repartition des evaluations de l'enseignant par type. */
    private function evaluationsParType($affectations): array
    {
        $affectationIds = $affectations->pluck('id');

        $parType = Evaluation::whereIn('affectation_id', $affectationIds)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $resultat = ['total' => 0];

        foreach (TypeEvaluationEnum::cases() as $type) {
            $n = (int) ($parType[$type->value] ?? 0);
            $resultat[$type->value] = $n;
            $resultat['total'] += $n;
        }

        return $resultat;
    }

    /**
     * Taux de saisie des notes : notes reellement saisies rapportees au nombre
     * attendu (evaluations x eleves de la classe concernee). Donne aussi le
     * nombre d'evaluations entierement saisies.
     */
    private function saisieNotes($affectations, AnneeScolaire $annee): array
    {
        $affectationIds = $affectations->pluck('id');

        $evaluations = Evaluation::whereIn('affectation_id', $affectationIds)
            ->with('affectation.classeMatiere')
            ->withCount('notes')
            ->get();

        // Eleves par classe, mis en cache pour eviter les requetes repetees.
        $elevesParClasse = [];
        $attendues = 0;
        $saisies   = 0;
        $completes = 0;

        foreach ($evaluations as $evaluation) {
            $classeId = $evaluation->affectation?->classeMatiere?->classe_id;
            if (!$classeId) {
                continue;
            }

            if (!isset($elevesParClasse[$classeId])) {
                $elevesParClasse[$classeId] = $this->elevesDeClasse($classeId, $annee);
            }

            $effectif   = $elevesParClasse[$classeId];
            $attendues += $effectif;
            $saisies   += $evaluation->notes_count;

            if ($effectif > 0 && $evaluation->notes_count >= $effectif) {
                $completes++;
            }
        }

        return [
            'notes_saisies'         => $saisies,
            'notes_attendues'       => $attendues,
            'taux'                  => $attendues > 0 ? round(($saisies / $attendues) * 100, 1) : 0.0,
            'evaluations_completes' => $completes,
            'evaluations_total'     => $evaluations->count(),
        ];
    }

    /**
     * Detail par cours (classe x matiere) : nombre d'evaluations et d'eleves.
     *
     * @return array<int, array{classe: string, matiere: string, evaluations: int, eleves: int}>
     */
    private function mesCours($affectations, AnneeScolaire $annee): array
    {
        return $affectations->map(function ($affectation) use ($annee) {
            $classeId = $affectation->classeMatiere?->classe_id;

            return [
                'classe'      => $affectation->classeMatiere?->classe?->nom ?? '—',
                'matiere'     => $affectation->classeMatiere?->matiere?->nom ?? '—',
                'evaluations' => Evaluation::where('affectation_id', $affectation->id)->count(),
                'eleves'      => $classeId ? $this->elevesDeClasse($classeId, $annee) : 0,
            ];
        })->all();
    }

    /** Nombre d'eleves inscrits (non annules) dans une classe pour l'annee. */
    private function elevesDeClasse(int $classeId, AnneeScolaire $annee): int
    {
        return Inscription::where('classe_id', $classeId)
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut_inscription', '!=', StatutInscriptionEnum::ANNULEE)
            ->count();
    }

    private function dashboardVide(): array
    {
        return [
            'annee_scolaire'          => null,
            'effectifs'               => [
                'eleves_inscrits' => 0, 'classes' => 0, 'niveaux' => Niveau::count(),
                'enseignants' => Enseignant::count(), 'surveillants' => Surveillant::count(),
                'tresoriers' => Tresorier::count(), 'tuteurs' => Tuteur::count(),
            ],
            'eleves_par_sexe'         => ['masculin' => 0, 'feminin' => 0],
            'eleves_par_niveau'       => [],
            'inscriptions_par_statut' => ['en_attente' => 0, 'validee' => 0, 'annulee' => 0, 'total' => 0],
            'finances'                => [
                'inscriptions' => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'mensualites'  => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'total'        => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'taux_recouvrement' => 0.0,
            ],
        ];
    }

    private function dashboardTresorierVide(): array
    {
        return [
            'annee_scolaire'         => null,
            'finances'               => [
                'inscriptions' => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'mensualites'  => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'total'        => ['du' => 0, 'encaisse' => 0, 'reste' => 0],
                'taux_recouvrement' => 0.0,
            ],
            'encaissements'          => [
                'aujourdhui' => ['montant' => 0, 'nombre' => 0],
                'ce_mois'    => ['montant' => 0, 'nombre' => 0],
                'annee'      => ['montant' => 0, 'nombre' => 0],
            ],
            'par_mode_paiement'      => array_map(
                fn ($m) => ['mode' => $m->value, 'montant' => 0],
                ModePaiementEnum::cases()
            ),
            'mensualites_par_statut' => ['paye' => 0, 'partiel' => 0, 'non_paye' => 0, 'total' => 0],
            'a_encaisser'            => ['inscriptions' => 0, 'mensualites' => 0],
        ];
    }

    private function dashboardEnseignantVide(?AnneeScolaire $annee): array
    {
        $typesVides = ['total' => 0];
        foreach (TypeEvaluationEnum::cases() as $type) {
            $typesVides[$type->value] = 0;
        }

        return [
            'annee_scolaire'       => $annee ? ['id' => $annee->id, 'nom' => $annee->nom] : null,
            'effectifs'            => ['affectations' => 0, 'classes' => 0, 'matieres' => 0, 'eleves' => 0],
            'evaluations_par_type' => $typesVides,
            'saisie_notes'         => [
                'notes_saisies' => 0, 'notes_attendues' => 0, 'taux' => 0.0,
                'evaluations_completes' => 0, 'evaluations_total' => 0,
            ],
            'mes_cours'            => [],
        ];
    }
}
