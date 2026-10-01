<?php

namespace App\Interfaces;

use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Presence;
use App\Models\SeanceAppel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Carbon\CarbonInterface;

interface AssiduiteRepositoryInterface
{
    /**
     * Les créneaux à tenir un jour donné, avec la séance déjà saisie si elle
     * existe. `$enseignantId` borne à un enseignant ; `$classeId` à une classe.
     */
    public function creneauxDuJour(CarbonInterface $date, ?int $enseignantId = null, ?int $classeId = null): Collection;

    public function findCreneau(int|string $id): EmploiDuTemps;

    public function findSeance(int|string $id): SeanceAppel;

    public function findPresence(int|string $id): Presence;

    /** La séance déjà tenue pour ce couple (créneau × date), s'il y en a une. */
    public function seanceExistante(int $creneauId, string $date): ?SeanceAppel;

    /** La période de l'année en cours qui couvre cette date, s'il y en a une. */
    public function periodePourDate(CarbonInterface $date): ?int;

    /** Les élèves inscrits (inscription validée, année en cours) dans la classe. */
    public function elevesForClasse(int|string $classeId): Collection;

    /**
     * Crée ou met à jour la séance et remplace ses anomalies en une
     * transaction. `$lignes` : [['eleve_id' => int, 'statut' => string,
     * 'minutes_retard' => ?int, 'motif' => ?string]].
     */
    public function enregistrerAppel(array $seanceData, array $lignes): SeanceAppel;

    // ------------------------------------------------------------------
    // Consultation
    // ------------------------------------------------------------------

    /**
     * Registre paginé des anomalies. `$classeIds` borne la vue d'un enseignant
     * à ses classes ; null signifie « aucune restriction ».
     */
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
    ): LengthAwarePaginator;

    public function findEleve(int|string $id): Eleve;

    /** Toutes les anomalies d'un élève, éventuellement bornées à une période. */
    public function presencesEleve(int|string $eleveId, ?int $periodeId = null): Collection;

    public function updatePresence(Presence $presence, array $data): Presence;

    public function deletePresence(Presence $presence): void;

    // ------------------------------------------------------------------
    // Agrégats
    // ------------------------------------------------------------------

    /**
     * Minutes d'absence et nombre de retards par élève, pour le bulletin.
     * Seules les séances effectivement tenues comptent.
     *
     * @return Collection<int, object{eleve_id:int, minutes:int, retards:int}>
     */
    public function agregatsPourBulletin(int $classeId, int $periodeId): Collection;

    /** Y a-t-il eu au moins un appel sur ce couple (classe × période) ? */
    public function existeSeanceFaite(int $classeId, int $periodeId): bool;

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    /** @return array{absents:int, retards:int, renvois:int} */
    public function compteursDuJour(CarbonInterface $date): array;

    /** @return array{faites:int, attendues:int, non_assurees:int} */
    public function tauxAppelsDuJour(CarbonInterface $date): array;

    /** Les créneaux du jour dont l'appel n'a pas été fait. */
    public function appelsManquants(CarbonInterface $date): Collection;

    /** Top des élèves par heures d'absence non justifiées sur la période. */
    public function recidivistes(?int $periodeId, int $limite = 10): Collection;

    public function compteAJustifier(int $joursAnciennete = 3): int;

    /** Heures d'absence par classe sur la période, rapportées à l'effectif. */
    public function repartitionParClasse(?int $periodeId): SupportCollection;

    /** Nombre d'absences par jour sur les N derniers jours. */
    public function tendance(CarbonInterface $jusqua, int $jours = 7): SupportCollection;
}
