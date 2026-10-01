<?php

namespace App\Repositories;

use App\Enums\JourSemaineEnum;
use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPresenceEnum;
use App\Enums\StatutSeanceEnum;
use App\Interfaces\AssiduiteRepositoryInterface;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Periode;
use App\Models\Presence;
use App\Models\SeanceAppel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AssiduiteRepository implements AssiduiteRepositoryInterface
{
    /**
     * `saisiePar` n'est pas chargee, pour la meme raison que `justifiePar`
     * plus bas : Eloquent serialiserait la relation sous la cle `saisie_par`,
     * qui deviendrait un objet utilisateur complet au lieu d'un identifiant.
     */
    private const RELATIONS = [
        'classe',
        'affectation.enseignant.user',
        'affectation.classeMatiere.matiere',
        'periode',
    ];

    /**
     * `justifiePar` n'est volontairement pas chargee : Eloquent serialise la
     * relation sous la meme cle que la colonne `justifie_par`, qui deviendrait
     * un objet utilisateur complet au lieu d'un identifiant — reponse alourdie
     * et donnees personnelles exposees sans motif.
     */
    private const RELATIONS_PRESENCE = [
        'eleve',
        'seance.classe',
        'seance.affectation.classeMatiere.matiere',
        'seance.periode',
        'media',
    ];

    public function creneauxDuJour(CarbonInterface $date, ?int $enseignantId = null, ?int $classeId = null): Collection
    {
        $jour = JourSemaineEnum::fromDate($date);

        // Dimanche : aucun creneau au programme, ce n'est pas une erreur.
        if ($jour === null) {
            return new Collection();
        }

        $creneaux = EmploiDuTemps::query()
            ->where('jour', $jour->value)
            ->with(['classe', 'affectation.enseignant.user', 'affectation.classeMatiere.matiere'])
            ->when($classeId, fn ($q, $id) => $q->where('classe_id', $id))
            ->when($enseignantId, fn ($q, $id) => $q
                ->whereHas('affectation', fn ($q) => $q->where('enseignant_id', $id)))
            ->orderBy('heure_debut')
            ->get();

        // La seance deja saisie, rattachee au creneau pour que l'appelant
        // sache d'un coup d'oeil ce qui reste a faire.
        $seances = SeanceAppel::query()
            ->whereIn('emploi_du_temps_id', $creneaux->pluck('id'))
            ->whereDate('date_seance', $date->toDateString())
            ->with('presences')
            ->get()
            ->keyBy('emploi_du_temps_id');

        return $creneaux->each(
            fn (EmploiDuTemps $c) => $c->setRelation('seance', $seances->get($c->id))
        );
    }

    public function findCreneau(int|string $id): EmploiDuTemps
    {
        return EmploiDuTemps::with([
            'classe',
            'affectation.enseignant.user',
            'affectation.classeMatiere.matiere',
        ])->findOrFail($id);
    }

    public function findSeance(int|string $id): SeanceAppel
    {
        return SeanceAppel::with([...self::RELATIONS, 'presences.eleve'])->findOrFail($id);
    }

    public function findPresence(int|string $id): Presence
    {
        return Presence::with(self::RELATIONS_PRESENCE)->findOrFail($id);
    }

    public function seanceExistante(int $creneauId, string $date): ?SeanceAppel
    {
        return SeanceAppel::query()
            ->where('emploi_du_temps_id', $creneauId)
            ->whereDate('date_seance', $date)
            ->with('presences')
            ->first();
    }

    public function periodePourDate(CarbonInterface $date): ?int
    {
        return Periode::query()
            ->whereDate('date_debut', '<=', $date->toDateString())
            ->whereDate('date_fin', '>=', $date->toDateString())
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->orderBy('ordre')
            ->value('id');
    }

    /** Même règle que EvaluationRepository::elevesForAffectation(). */
    public function elevesForClasse(int|string $classeId): Collection
    {
        return Eleve::query()
            ->whereHas('inscriptions', fn ($q) => $q
                ->where('classe_id', $classeId)
                ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)
                ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function enregistrerAppel(array $seanceData, array $lignes): SeanceAppel
    {
        $id = DB::transaction(function () use ($seanceData, $lignes) {
            $seance = SeanceAppel::updateOrCreate(
                [
                    'emploi_du_temps_id' => $seanceData['emploi_du_temps_id'],
                    'date_seance'        => $seanceData['date_seance'],
                ],
                $seanceData,
            );

            // Les anomalies sont remplacees, pas fusionnees : un eleve retire
            // de la liste redevient present. forceDelete pour que l'index
            // unique partiel ne bute pas sur une ligne soft-deleted.
            Presence::where('seance_appel_id', $seance->id)->forceDelete();

            foreach ($lignes as $ligne) {
                $statut = $ligne['statut'] instanceof StatutPresenceEnum
                    ? $ligne['statut']
                    : StatutPresenceEnum::from($ligne['statut']);

                Presence::create([
                    'seance_appel_id' => $seance->id,
                    'eleve_id'        => $ligne['eleve_id'],
                    'statut'          => $statut,
                    // Les minutes n'ont de sens que pour un retard.
                    'minutes_retard'  => $statut === StatutPresenceEnum::RETARD
                        ? ($ligne['minutes_retard'] ?? null)
                        : null,
                    'motif'           => $ligne['motif'] ?? null,
                ]);
            }

            return $seance->id;
        });

        return $this->findSeance($id);
    }

    // ------------------------------------------------------------------
    // Consultation
    // ------------------------------------------------------------------

    public function paginatePresences(
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $eleveId = null,
        ?string $statut = null,
        ?bool $justifie = null,
        ?string $du = null,
        ?string $au = null,
        ?array $classeIds = null,
    ): LengthAwarePaginator {
        // Les colonnes sont qualifiees : le join sur `seances_appel` rend
        // `statut` et `deleted_at` ambigus, les deux tables les portent.
        return Presence::query()
            ->with(self::RELATIONS_PRESENCE)
            ->when($eleveId, fn ($q, $id) => $q->where('presences.eleve_id', $id))
            ->when($statut, fn ($q, $s) => $q->where('presences.statut', $s))
            ->when($justifie !== null, fn ($q) => $q->where('presences.justifie', $justifie))
            ->whereHas('seance', function ($q) use ($classeId, $du, $au, $classeIds) {
                $q->when($classeId, fn ($q, $id) => $q->where('classe_id', $id))
                  ->when($du, fn ($q, $d) => $q->whereDate('date_seance', '>=', $d))
                  ->when($au, fn ($q, $d) => $q->whereDate('date_seance', '<=', $d))
                  // Un tableau vide ne doit rien renvoyer : on teste sur null.
                  ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds));
            })
            ->when($search, fn ($q) => $q->whereHas('eleve', function ($q) use ($search) {
                $motif = "%{$search}%";

                $q->where('nom', 'ilike', $motif)
                  ->orWhere('prenom', 'ilike', $motif)
                  ->orWhere('matricule', 'ilike', $motif)
                  // Nom complet dans les deux ordres : « Awa Diop » comme
                  // « Diop Awa » doivent trouver le meme eleve.
                  ->orWhereRaw("(prenom || ' ' || nom) ilike ?", [$motif])
                  ->orWhereRaw("(nom || ' ' || prenom) ilike ?", [$motif]);
            }))
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->orderByDesc('seances_appel.date_seance')
            ->orderByDesc('seances_appel.heure_debut')
            ->select('presences.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findEleve(int|string $id): Eleve
    {
        return Eleve::with('classeActuelle')->findOrFail($id);
    }

    public function presencesEleve(int|string $eleveId, ?int $periodeId = null): Collection
    {
        // Colonnes qualifiees : le join rend `eleve_id` et `statut` ambigus.
        return Presence::query()
            ->with(self::RELATIONS_PRESENCE)
            ->where('presences.eleve_id', $eleveId)
            ->whereHas('seance', fn ($q) => $q
                ->where('statut', StatutSeanceEnum::FAITE)
                ->when($periodeId, fn ($q, $id) => $q->where('periode_id', $id)))
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->orderByDesc('seances_appel.date_seance')
            ->select('presences.*')
            ->get();
    }

    public function updatePresence(Presence $presence, array $data): Presence
    {
        $presence->update($data);

        return $presence->fresh(self::RELATIONS_PRESENCE);
    }

    public function deletePresence(Presence $presence): void
    {
        $presence->delete();
    }

    // ------------------------------------------------------------------
    // Agrégats
    // ------------------------------------------------------------------

    public function agregatsPourBulletin(int $classeId, int $periodeId): Collection
    {
        // Agregation en SQL : sommer des minutes en PHP sur des milliers de
        // lignes chargees n'aurait aucun interet.
        return Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->where('seances_appel.classe_id', $classeId)
            ->where('seances_appel.periode_id', $periodeId)
            ->where('seances_appel.statut', StatutSeanceEnum::FAITE->value)
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at')
            ->groupBy('presences.eleve_id')
            ->selectRaw('presences.eleve_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN presences.statut IN (?, ?) THEN seances_appel.duree_minutes ELSE 0 END), 0) AS minutes',
                [StatutPresenceEnum::ABSENT->value, StatutPresenceEnum::RENVOYE->value]
            )
            ->selectRaw(
                'COUNT(CASE WHEN presences.statut = ? THEN 1 END) AS retards',
                [StatutPresenceEnum::RETARD->value]
            )
            ->get();
    }

    public function existeSeanceFaite(int $classeId, int $periodeId): bool
    {
        return SeanceAppel::query()
            ->where('classe_id', $classeId)
            ->where('periode_id', $periodeId)
            ->where('statut', StatutSeanceEnum::FAITE)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    public function compteursDuJour(CarbonInterface $date): array
    {
        $base = fn () => Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->whereDate('seances_appel.date_seance', $date->toDateString())
            ->where('seances_appel.statut', StatutSeanceEnum::FAITE->value)
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at');

        return [
            // Distinct : un eleve absent 4 heures reste UNE personne absente.
            'absents' => (int) $base()
                ->where('presences.statut', StatutPresenceEnum::ABSENT->value)
                ->distinct('presences.eleve_id')
                ->count('presences.eleve_id'),
            'retards' => (int) $base()
                ->where('presences.statut', StatutPresenceEnum::RETARD->value)
                ->count(),
            'renvois' => (int) $base()
                ->where('presences.statut', StatutPresenceEnum::RENVOYE->value)
                ->count(),
        ];
    }

    public function tauxAppelsDuJour(CarbonInterface $date): array
    {
        $jour = JourSemaineEnum::fromDate($date);

        if ($jour === null) {
            return ['faites' => 0, 'attendues' => 0, 'non_assurees' => 0];
        }

        $attendues = EmploiDuTemps::where('jour', $jour->value)->count();

        $parStatut = SeanceAppel::query()
            ->whereDate('date_seance', $date->toDateString())
            ->groupBy('statut')
            ->selectRaw('statut, COUNT(*) as total')
            ->pluck('total', 'statut');

        return [
            'faites'       => (int) ($parStatut[StatutSeanceEnum::FAITE->value] ?? 0),
            'attendues'    => $attendues,
            'non_assurees' => (int) ($parStatut[StatutSeanceEnum::NON_ASSUREE->value] ?? 0),
        ];
    }

    public function appelsManquants(CarbonInterface $date): Collection
    {
        $jour = JourSemaineEnum::fromDate($date);

        if ($jour === null) {
            return new Collection();
        }

        // Les creneaux du jour sans aucune seance saisie a cette date.
        return EmploiDuTemps::query()
            ->where('jour', $jour->value)
            ->with(['classe', 'affectation.enseignant.user', 'affectation.classeMatiere.matiere'])
            ->whereDoesntHave('seances', fn ($q) => $q->whereDate('date_seance', $date->toDateString()))
            ->orderBy('heure_debut')
            ->get();
    }

    public function recidivistes(?int $periodeId, int $limite = 10): Collection
    {
        return Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->join('eleves', 'eleves.id', '=', 'presences.eleve_id')
            ->where('seances_appel.statut', StatutSeanceEnum::FAITE->value)
            ->when($periodeId, fn ($q, $id) => $q->where('seances_appel.periode_id', $id))
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at')
            ->groupBy('presences.eleve_id', 'eleves.nom', 'eleves.prenom', 'eleves.matricule')
            ->selectRaw('presences.eleve_id, eleves.nom, eleves.prenom, eleves.matricule')
            // Le classement porte sur les NON justifiees : c'est le decrochage
            // qui appelle une convocation, pas l'eleve malade avec certificats.
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN presences.statut IN (?, ?) AND presences.justifie = false THEN seances_appel.duree_minutes ELSE 0 END), 0) AS minutes_non_justifiees',
                [StatutPresenceEnum::ABSENT->value, StatutPresenceEnum::RENVOYE->value]
            )
            ->selectRaw(
                'COUNT(CASE WHEN presences.statut = ? THEN 1 END) AS retards',
                [StatutPresenceEnum::RETARD->value]
            )
            ->selectRaw('MAX(seances_appel.date_seance) AS derniere_absence')
            ->havingRaw(
                'SUM(CASE WHEN presences.statut IN (?, ?) AND presences.justifie = false THEN seances_appel.duree_minutes ELSE 0 END) > 0',
                [StatutPresenceEnum::ABSENT->value, StatutPresenceEnum::RENVOYE->value]
            )
            ->orderByDesc('minutes_non_justifiees')
            ->limit($limite)
            ->get();
    }

    public function compteAJustifier(int $joursAnciennete = 3): int
    {
        return Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->where('presences.justifie', false)
            ->whereIn('presences.statut', [
                StatutPresenceEnum::ABSENT->value,
                StatutPresenceEnum::RENVOYE->value,
            ])
            ->whereDate('seances_appel.date_seance', '<=', now()->subDays($joursAnciennete)->toDateString())
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at')
            ->count();
    }

    public function repartitionParClasse(?int $periodeId): SupportCollection
    {
        $heures = Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->join('classes', 'classes.id', '=', 'seances_appel.classe_id')
            ->where('seances_appel.statut', StatutSeanceEnum::FAITE->value)
            ->whereIn('presences.statut', [
                StatutPresenceEnum::ABSENT->value,
                StatutPresenceEnum::RENVOYE->value,
            ])
            ->when($periodeId, fn ($q, $id) => $q->where('seances_appel.periode_id', $id))
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at')
            ->groupBy('seances_appel.classe_id', 'classes.nom')
            ->selectRaw('seances_appel.classe_id, classes.nom AS classe_nom')
            ->selectRaw('COALESCE(SUM(seances_appel.duree_minutes), 0) AS minutes')
            ->get();

        // L'effectif normalise le resultat : sans lui, les grandes classes
        // paraitraient toujours les plus absentéistes.
        $effectifs = Classe::query()
            ->whereIn('id', $heures->pluck('classe_id'))
            ->withCount(['inscriptions as effectif' => fn ($q) => $q
                ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)])
            ->pluck('effectif', 'id');

        return $heures->map(function ($ligne) use ($effectifs) {
            $effectif = (int) ($effectifs[$ligne->classe_id] ?? 0);
            $heures   = round(((int) $ligne->minutes) / 60, 1);

            return [
                'classe_id'        => (int) $ligne->classe_id,
                'classe_nom'       => $ligne->classe_nom,
                'heures'           => $heures,
                'effectif'         => $effectif,
                'heures_par_eleve' => $effectif > 0 ? round($heures / $effectif, 2) : 0.0,
            ];
        })->sortByDesc('heures_par_eleve')->values();
    }

    public function tendance(CarbonInterface $jusqua, int $jours = 7): SupportCollection
    {
        $debut = $jusqua->copy()->subDays($jours - 1)->toDateString();

        $parJour = Presence::query()
            ->join('seances_appel', 'seances_appel.id', '=', 'presences.seance_appel_id')
            ->where('seances_appel.statut', StatutSeanceEnum::FAITE->value)
            ->whereIn('presences.statut', [
                StatutPresenceEnum::ABSENT->value,
                StatutPresenceEnum::RENVOYE->value,
            ])
            ->whereBetween('seances_appel.date_seance', [$debut, $jusqua->toDateString()])
            ->whereNull('seances_appel.deleted_at')
            ->whereNull('presences.deleted_at')
            ->groupBy('seances_appel.date_seance')
            ->selectRaw('seances_appel.date_seance, COUNT(*) AS total')
            ->pluck('total', 'date_seance');

        // Serie complete : les jours sans absence doivent apparaitre a zero,
        // sinon le graphique laisse croire a des donnees manquantes.
        return collect(range($jours - 1, 0))->map(function (int $recul) use ($jusqua, $parJour) {
            $jour = $jusqua->copy()->subDays($recul);
            $cle  = $jour->toDateString();

            return [
                'date'    => $cle,
                'libelle' => $jour->translatedFormat('D d/m'),
                'total'   => (int) ($parJour[$cle] ?? 0),
            ];
        });
    }
}
