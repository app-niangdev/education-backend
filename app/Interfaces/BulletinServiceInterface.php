<?php

namespace App\Interfaces;

use App\Models\Bulletin;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BulletinServiceInterface
{
    /** Les référentiels de saisie : statuts, mentions, décisions, distinctions. */
    public function meta(): array;

    public function list(
        User $authUser,
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $periodeId = null,
        ?string $statut = null,
    ): LengthAwarePaginator;

    public function find(User $authUser, int|string $id): Bulletin;

    /**
     * Génère ou recalcule les brouillons de toute la classe pour une période.
     * Refuse d'écraser des bulletins déjà publiés.
     *
     * @return array{bulletins: mixed, saisie_encore_ouverte: bool, ...}
     */
    public function genererPourClasse(int|string $classeId, int|string $periodeId, User $authUser): array;

    public function updateConseil(int|string $id, array $data, User $authUser): Bulletin;

    /** @return int nombre de bulletins publiés */
    public function publierClasse(int|string $classeId, int|string $periodeId, User $authUser): int;

    public function publierUn(int|string $id, User $authUser): Bulletin;

    /** @return int nombre de bulletins dépubliés */
    public function depublierClasse(int|string $classeId, int|string $periodeId, User $authUser): int;

    public function depublierUn(int|string $id, User $authUser): Bulletin;

    /** Données du gabarit PDF d'un bulletin. */
    public function dataForPdf(User $authUser, int|string $id): array;

    /** Données du gabarit PDF groupé d'une classe. */
    public function dataForPdfClasse(User $authUser, int|string $classeId, int|string $periodeId): array;
}
