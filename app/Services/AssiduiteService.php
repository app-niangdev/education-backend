<?php

namespace App\Services;

use App\Enums\JourSemaineEnum;
use App\Enums\RoleEnum;
use App\Enums\StatutPresenceEnum;
use App\Enums\StatutSeanceEnum;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\AssiduiteRepositoryInterface;
use App\Interfaces\AssiduiteServiceInterface;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Presence;
use App\Models\SeanceAppel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Assiduité : appel par créneau, justification, suivi.
 *
 * L'enseignant fait l'appel sur ses propres créneaux et dispose d'une semaine
 * pour se corriger ; au-delà il passe par la vie scolaire. Le surveillant voit
 * toute l'école, justifie et corrige sans limite de temps — un justificatif
 * peut être remis quinze jours après l'absence. Le trésorier n'a aucun accès :
 * les résultats scolaires et l'assiduité ne le regardent pas.
 */
class AssiduiteService implements AssiduiteServiceInterface
{
    /**
     * Passé ce délai, un enseignant ne peut plus toucher à son appel.
     * Évite qu'un trimestre entier soit réécrit après publication des
     * bulletins, sans bloquer la régularisation d'un oubli de la semaine.
     */
    private const JOURS_SAISIE_ENSEIGNANT = 7;

    public function __construct(
        private readonly AssiduiteRepositoryInterface $repository,
        private readonly ActivityLogServiceInterface  $activityLog,
    ) {}

    public function meta(): array
    {
        return [
            'statuts_presence' => $this->casesToOptions(StatutPresenceEnum::cases()),
            'statuts_seance'   => $this->casesToOptions(StatutSeanceEnum::cases()),
            'jours_saisie_enseignant' => self::JOURS_SAISIE_ENSEIGNANT,
        ];
    }

    // ------------------------------------------------------------------
    // Appel
    // ------------------------------------------------------------------

    public function creneauxDuJour(User $authUser, string $date, ?int $classeId = null): array
    {
        $this->assertPeutConsulter($authUser);

        $jour     = Carbon::parse($date)->startOfDay();
        $creneaux = $this->repository->creneauxDuJour(
            $jour,
            $this->enseignantIdIfTeacher($authUser),
            $classeId,
        );

        return [
            'date'            => $jour->toDateString(),
            'jour'            => JourSemaineEnum::fromDate($jour)?->libelle(),
            'est_dimanche'    => JourSemaineEnum::fromDate($jour) === null,
            'creneaux'        => $creneaux,
            'periode_id'      => $this->repository->periodePourDate($jour),
            // Le front doit prévenir : l'appel sera enregistré mais ne comptera
            // dans aucun bulletin.
            'hors_periode'    => $this->repository->periodePourDate($jour) === null,
        ];
    }

    public function feuilleAppel(User $authUser, int|string $creneauId, string $date): array
    {
        $creneau = $this->repository->findCreneau($creneauId);
        $jour    = Carbon::parse($date)->startOfDay();

        $this->assertPeutSaisirAppel($authUser, $creneau, $jour, lectureSeule: true);
        $this->assertCreneauCorrespondAuJour($creneau, $jour);

        $seance   = $this->repository->seanceExistante((int) $creneau->id, $jour->toDateString());
        $eleves   = $this->repository->elevesForClasse($creneau->classe_id);
        $parEleve = $seance?->presences->keyBy('eleve_id');

        $lignes = $eleves->map(fn (Eleve $eleve) => [
            'eleve_id'       => $eleve->id,
            'matricule'      => $eleve->matricule,
            'nom_complet'    => $eleve->nom_complet,
            'statut'         => $parEleve?->get($eleve->id)?->statut?->value,
            'minutes_retard' => $parEleve?->get($eleve->id)?->minutes_retard,
            'motif'          => $parEleve?->get($eleve->id)?->motif,
            'justifie'       => (bool) ($parEleve?->get($eleve->id)?->justifie ?? false),
        ])->values();

        return [
            'creneau'      => $creneau,
            'date'         => $jour->toDateString(),
            'seance'       => $seance?->makeHidden('presences'),
            'lignes'       => $lignes,
            'hors_periode' => $this->repository->periodePourDate($jour) === null,
            'modifiable'   => $this->peutModifier($authUser, $jour),
        ];
    }

    public function enregistrerAppel(array $data, User $authUser): SeanceAppel
    {
        $creneau = $this->repository->findCreneau($data['emploi_du_temps_id']);
        $jour    = Carbon::parse($data['date_seance'])->startOfDay();

        $this->assertPeutSaisirAppel($authUser, $creneau, $jour);
        $this->assertCreneauCorrespondAuJour($creneau, $jour);

        $statutSeance = StatutSeanceEnum::from($data['statut_seance'] ?? StatutSeanceEnum::FAITE->value);

        // Un cours non assuré ne relève aucune anomalie : personne n'était
        // censé être là.
        $lignes = $statutSeance === StatutSeanceEnum::NON_ASSUREE
            ? []
            : $this->filtrerLignes($data['lignes'] ?? [], $creneau);

        $seance = $this->repository->enregistrerAppel([
            'emploi_du_temps_id' => $creneau->id,
            'classe_id'          => $creneau->classe_id,
            'affectation_id'     => $creneau->affectation_id,
            'annee_scolaire_id'  => $creneau->classe?->annee_scolaire_id,
            'periode_id'         => $this->repository->periodePourDate($jour),
            'date_seance'        => $jour->toDateString(),
            'heure_debut'        => $creneau->heure_debut,
            'heure_fin'          => $creneau->heure_fin,
            'duree_minutes'      => $this->dureeEnMinutes($creneau),
            'statut'             => $statutSeance,
            'saisie_par'         => $authUser->id,
            'saisie_le'          => Carbon::now(),
            'commentaire'        => $data['commentaire'] ?? null,
        ], $lignes);

        $this->activityLog->log(
            user:        $authUser,
            action:      'created',
            module:      'assiduite',
            description: "Appel du {$jour->format('d/m/Y')} — {$seance->matiere_nom} en « {$creneau->classe?->nom} » ("
                . count($lignes) . ' anomalie(s))',
            subject:     $seance,
            newValues:   ['statut' => $statutSeance->value, 'anomalies' => count($lignes)],
        );

        return $seance;
    }

    /**
     * Écarte les élèves qui ne sont pas dans la classe et les lignes sans
     * anomalie : un élève présent ne produit aucune ligne.
     */
    private function filtrerLignes(array $lignes, EmploiDuTemps $creneau): array
    {
        $autorises = $this->repository
            ->elevesForClasse($creneau->classe_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $retenues = [];

        foreach ($lignes as $ligne) {
            $statut = $ligne['statut'] ?? null;

            if ($statut === null || $statut === '') {
                continue; // présent : rien à enregistrer
            }

            if (!in_array((int) $ligne['eleve_id'], $autorises, true)) {
                abort(422, "Un élève ne fait pas partie de cette classe (id {$ligne['eleve_id']}).");
            }

            $retenues[] = $ligne;
        }

        return $retenues;
    }

    // ------------------------------------------------------------------
    // Consultation
    // ------------------------------------------------------------------

    public function list(
        User $authUser,
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $eleveId = null,
        ?string $statut = null,
        ?bool $justifie = null,
        ?string $du = null,
        ?string $au = null,
    ): LengthAwarePaginator {
        $this->assertPeutConsulter($authUser);

        return $this->repository->paginatePresences(
            perPage:   $perPage,
            search:    $search,
            classeId:  $classeId,
            eleveId:   $eleveId,
            statut:    $statut,
            justifie:  $justifie,
            du:        $du,
            au:        $au,
            classeIds: $this->classeIdsIfTeacher($authUser),
        );
    }

    public function find(User $authUser, int|string $id): Presence
    {
        $presence = $this->repository->findPresence($id);
        $this->assertPeutConsulter($authUser, $presence);

        return $presence;
    }

    public function justifier(int|string $id, array $data, ?UploadedFile $justificatif, User $authUser): Presence
    {
        $this->assertPeutJustifier($authUser);

        $presence = $this->repository->findPresence($id);
        $avant    = $presence->only(['justifie', 'motif']);

        $justifie = (bool) ($data['justifie'] ?? false);

        $presence = $this->repository->updatePresence($presence, [
            'justifie'     => $justifie,
            'motif'        => $data['motif'] ?? $presence->motif,
            // La traçabilité n'a de sens que tant que la justification tient.
            'justifie_par' => $justifie ? $authUser->id : null,
            'justifie_le'  => $justifie ? Carbon::now() : null,
        ]);

        if ($justificatif !== null) {
            $presence->addMedia($justificatif)->toMediaCollection('justificatif');
            $presence = $presence->fresh();
        }

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'assiduite',
            description: ($justifie ? 'Justification' : 'Retrait de justification')
                . " — {$presence->eleve?->nom_complet}, {$presence->statut_libelle} du "
                . ($presence->seance?->date_seance?->format('d/m/Y') ?? '?'),
            subject:     $presence,
            oldValues:   $avant,
            newValues:   $presence->only(['justifie', 'motif']),
        );

        return $presence;
    }

    public function corriger(int|string $id, array $data, User $authUser): Presence
    {
        $this->assertPeutJustifier($authUser);

        $presence = $this->repository->findPresence($id);
        $avant    = $presence->only(['statut', 'minutes_retard', 'motif']);

        $statut = isset($data['statut'])
            ? StatutPresenceEnum::from($data['statut'])
            : $presence->statut;

        $presence = $this->repository->updatePresence($presence, [
            'statut'         => $statut,
            // Les minutes ne veulent rien dire hors d'un retard.
            'minutes_retard' => $statut === StatutPresenceEnum::RETARD
                ? ($data['minutes_retard'] ?? $presence->minutes_retard)
                : null,
            'motif'          => $data['motif'] ?? $presence->motif,
        ]);

        $this->activityLog->log(
            user:        $authUser,
            action:      'updated',
            module:      'assiduite',
            description: "Correction — {$presence->eleve?->nom_complet} : {$presence->statut_libelle} le "
                . ($presence->seance?->date_seance?->format('d/m/Y') ?? '?'),
            subject:     $presence,
            oldValues:   $avant,
            newValues:   $presence->only(['statut', 'minutes_retard', 'motif']),
        );

        return $presence;
    }

    public function supprimer(int|string $id, User $authUser): void
    {
        $this->assertPeutJustifier($authUser);

        $presence = $this->repository->findPresence($id);
        $avant    = $presence->toArray();
        $libelle  = "{$presence->eleve?->nom_complet} — {$presence->statut_libelle}";

        $this->repository->deletePresence($presence);

        $this->activityLog->log(
            user:        $authUser,
            action:      'deleted',
            module:      'assiduite',
            description: "Suppression d'une anomalie d'assiduité : {$libelle}",
            subject:     $presence,
            oldValues:   $avant,
        );
    }

    public function ficheEleve(User $authUser, int|string $eleveId, ?int $periodeId = null): array
    {
        $this->assertPeutConsulter($authUser);

        $eleve      = $this->repository->findEleve($eleveId);
        $presences  = $this->repository->presencesEleve($eleveId, $periodeId);

        $minutesTotal   = 0;
        $minutesNonJust = 0;
        $retards        = 0;
        $renvois        = 0;

        foreach ($presences as $presence) {
            $duree = (int) ($presence->seance?->duree_minutes ?? 0);

            if ($presence->statut?->compteCommeAbsence()) {
                $minutesTotal += $duree;

                if (!$presence->justifie) {
                    $minutesNonJust += $duree;
                }
            }

            if ($presence->statut === StatutPresenceEnum::RETARD) {
                $retards++;
            }

            if ($presence->statut === StatutPresenceEnum::RENVOYE) {
                $renvois++;
            }
        }

        return [
            'eleve'      => $eleve,
            'periode_id' => $periodeId,
            'totaux'     => [
                'heures_absence'               => round($minutesTotal / 60, 1),
                'heures_absence_non_justifiee' => round($minutesNonJust / 60, 1),
                'retards'                      => $retards,
                'renvois'                      => $renvois,
                'anomalies'                    => $presences->count(),
            ],
            'presences'  => $presences,
        ];
    }

    // ------------------------------------------------------------------
    // Dashboard et PDF
    // ------------------------------------------------------------------

    public function dashboardSurveillant(User $authUser, ?string $date = null, ?int $periodeId = null): array
    {
        $this->assertPeutSuperviser($authUser);

        $jour  = $date !== null ? Carbon::parse($date)->startOfDay() : Carbon::now()->startOfDay();
        $taux  = $this->repository->tauxAppelsDuJour($jour);
        $reste = max(0, $taux['attendues'] - $taux['non_assurees']);

        return [
            'date'       => $jour->toDateString(),
            'compteurs'  => $this->repository->compteursDuJour($jour),
            'appels'     => [
                ...$taux,
                // Un cours non assuré ne doit pas peser sur le taux : personne
                // n'avait à faire l'appel.
                'taux' => $reste > 0 ? round(($taux['faites'] / $reste) * 100, 1) : 0.0,
            ],
            'appels_manquants' => $this->repository->appelsManquants($jour),
            'recidivistes'     => $this->repository->recidivistes($periodeId),
            'a_justifier'      => $this->repository->compteAJustifier(),
            'par_classe'       => $this->repository->repartitionParClasse($periodeId),
            'tendance'         => $this->repository->tendance($jour),
        ];
    }

    public function dataForPdfFicheEleve(User $authUser, int|string $eleveId, ?int $periodeId = null): array
    {
        return $this->ficheEleve($authUser, $eleveId, $periodeId);
    }

    public function dataForPdfFeuilleVierge(User $authUser, int|string $classeId, string $date): array
    {
        $this->assertPeutConsulter($authUser);

        $jour     = Carbon::parse($date)->startOfDay();
        $creneaux = $this->repository->creneauxDuJour($jour, null, (int) $classeId);
        $eleves   = $this->repository->elevesForClasse($classeId);

        if ($eleves->isEmpty()) {
            abort(422, "Aucun élève inscrit dans cette classe pour l'année en cours.");
        }

        return [
            'classe'   => Classe::with('niveau')->findOrFail($classeId),
            'date'     => $jour,
            'jour'     => JourSemaineEnum::fromDate($jour)?->libelle(),
            'creneaux' => $creneaux,
            'eleves'   => $eleves,
        ];
    }

    // ------------------------------------------------------------------
    // Autorisations
    // ------------------------------------------------------------------

    private function isTeacher(User $user): bool
    {
        return $user->role_id === RoleEnum::Teacher->value;
    }

    /**
     * L'id enseignant de l'utilisateur s'il est prof, sinon null.
     * Le -1 est délibéré : un null désactiverait le filtre en aval et un prof
     * sans profil enseignant verrait tout.
     */
    private function enseignantIdIfTeacher(User $user): ?int
    {
        if (!$this->isTeacher($user)) {
            return null;
        }

        return $user->enseignant?->id ?? -1;
    }

    /** Les classes visibles par un enseignant ; null = aucune restriction. */
    private function classeIdsIfTeacher(User $user): ?array
    {
        if (!$this->isTeacher($user)) {
            return null;
        }

        $enseignantId = $user->enseignant?->id;

        if ($enseignantId === null) {
            return [-1];
        }

        return Classe::query()
            ->whereHas('classeMatieres.affectation', fn ($q) => $q->where('enseignant_id', $enseignantId))
            ->pluck('id')
            ->all();
    }

    /** Le trésorier n'a aucun motif d'accéder à l'assiduité. */
    private function assertPeutConsulter(User $user, ?Presence $presence = null): void
    {
        if ($user->role_id === RoleEnum::Treasurer->value) {
            abort(403, 'Accès non autorisé.');
        }

        if ($presence === null) {
            return;
        }

        $classeIds = $this->classeIdsIfTeacher($user);

        if ($classeIds !== null && !in_array((int) $presence->seance?->classe_id, $classeIds, true)) {
            abort(403, "Vous ne pouvez consulter que l'assiduité de vos propres classes.");
        }
    }

    /** Justifier et corriger sont des actes de la vie scolaire. */
    private function assertPeutJustifier(User $user): void
    {
        $autorises = [
            RoleEnum::Admin->value,
            RoleEnum::Manager->value,
            RoleEnum::Supervisor->value,
        ];

        if (!in_array($user->role_id, $autorises, true)) {
            abort(403, "Seule la vie scolaire peut justifier ou corriger une absence.");
        }
    }

    private function assertPeutSuperviser(User $user): void
    {
        $autorises = [
            RoleEnum::Admin->value,
            RoleEnum::Manager->value,
            RoleEnum::Supervisor->value,
        ];

        if (!in_array($user->role_id, $autorises, true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * L'enseignant ne fait l'appel que sur ses créneaux et dans la semaine.
     * En lecture seule, le délai n'est pas opposé : il doit pouvoir consulter
     * un appel ancien sans pouvoir le modifier.
     */
    private function assertPeutSaisirAppel(
        User $user,
        EmploiDuTemps $creneau,
        Carbon $date,
        bool $lectureSeule = false,
    ): void {
        $this->assertPeutConsulter($user);

        if (!$this->isTeacher($user)) {
            return; // admin, manager, surveillant : aucune restriction
        }

        if ((int) $creneau->affectation?->enseignant_id !== (int) $user->enseignant?->id) {
            abort(403, "Vous ne pouvez faire l'appel que sur vos propres créneaux.");
        }

        if (!$lectureSeule && !$this->peutModifier($user, $date)) {
            abort(422, "Cet appel date de plus de " . self::JOURS_SAISIE_ENSEIGNANT
                . " jours : demandez au surveillant de le régulariser.");
        }
    }

    private function peutModifier(User $user, Carbon $date): bool
    {
        if (!$this->isTeacher($user)) {
            return true;
        }

        return $date->greaterThanOrEqualTo(
            Carbon::now()->startOfDay()->subDays(self::JOURS_SAISIE_ENSEIGNANT)
        );
    }

    /** Un créneau du mardi ne peut pas être appelé un jeudi. */
    private function assertCreneauCorrespondAuJour(EmploiDuTemps $creneau, Carbon $date): void
    {
        $jour = JourSemaineEnum::fromDate($date);

        if ($jour === null) {
            abort(422, "Aucun cours n'est programmé le dimanche.");
        }

        if ($creneau->jour !== $jour) {
            abort(422, "Ce créneau a lieu le {$creneau->jour->libelle()}, pas le {$jour->libelle()}.");
        }
    }

    private function dureeEnMinutes(EmploiDuTemps $creneau): int
    {
        $debut = Carbon::parse($creneau->heure_debut);
        $fin   = Carbon::parse($creneau->heure_fin);

        return max(0, $debut->diffInMinutes($fin));
    }

    /** @param array<int, \BackedEnum> $cases */
    private function casesToOptions(array $cases): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->libelle()],
            $cases,
        );
    }
}
