<?php

namespace App\Interfaces;

use App\Models\Presence;
use App\Models\SeanceAppel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface AssiduiteServiceInterface
{
    /** Les référentiels de saisie : statuts de présence et de séance. */
    public function meta(): array;

    /**
     * Les créneaux d'une journée. Un enseignant ne voit que les siens ; les
     * autres rôles voient toute l'école, éventuellement filtrée par classe.
     */
    public function creneauxDuJour(User $authUser, string $date, ?int $classeId = null): array;

    /**
     * La feuille d'appel d'un créneau à une date : les élèves de la classe et
     * les anomalies déjà saisies.
     */
    public function feuilleAppel(User $authUser, int|string $creneauId, string $date): array;

    /** Enregistre ou corrige l'appel d'un créneau. */
    public function enregistrerAppel(array $data, User $authUser): SeanceAppel;

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
    ): LengthAwarePaginator;

    public function find(User $authUser, int|string $id): Presence;

    /** Marque une anomalie comme justifiée ou non, avec pièce jointe éventuelle. */
    public function justifier(int|string $id, array $data, ?UploadedFile $justificatif, User $authUser): Presence;

    /** Corrige le statut d'une anomalie (acte du surveillant). */
    public function corriger(int|string $id, array $data, User $authUser): Presence;

    public function supprimer(int|string $id, User $authUser): void;

    /** Le récapitulatif d'assiduité d'un élève, éventuellement sur une période. */
    public function ficheEleve(User $authUser, int|string $eleveId, ?int $periodeId = null): array;

    // ------------------------------------------------------------------
    // Dashboard et PDF
    // ------------------------------------------------------------------

    public function dashboardSurveillant(User $authUser, ?string $date = null, ?int $periodeId = null): array;

    public function dataForPdfFicheEleve(User $authUser, int|string $eleveId, ?int $periodeId = null): array;

    /** Feuille d'appel vierge, à imprimer pour un appel papier. */
    public function dataForPdfFeuilleVierge(User $authUser, int|string $classeId, string $date): array;
}
