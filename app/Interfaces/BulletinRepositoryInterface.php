<?php

namespace App\Interfaces;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Periode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface BulletinRepositoryInterface
{
    /**
     * Liste paginée des bulletins, filtrable par classe, période et statut.
     * `$classeIds` borne la vue d'un enseignant à ses propres classes ;
     * null signifie « aucune restriction ».
     */
    public function paginate(
        int $perPage,
        string $search,
        ?int $classeId = null,
        ?int $periodeId = null,
        ?string $statut = null,
        ?array $classeIds = null,
    ): LengthAwarePaginator;

    public function findById(int|string $id): Bulletin;

    public function findClasse(int|string $classeId): Classe;

    public function findPeriode(int|string $periodeId): Periode;

    /** Tous les bulletins d'une classe pour une période, lignes comprises. */
    public function forClasseEtPeriode(int|string $classeId, int|string $periodeId): Collection;

    /** Les élèves inscrits (inscription validée, année en cours) dans la classe. */
    public function elevesForClasse(int|string $classeId): Collection;

    /** Le programme de la classe : matières et coefficients, dans l'ordre d'affichage. */
    public function programmeDeClasse(int|string $classeId): Collection;

    /** Les évaluations de la période pour cette classe, avec leurs notes préchargées. */
    public function evaluationsAvecNotes(int|string $classeId, int|string $periodeId): Collection;

    /** Un bulletin publié existe-t-il pour ce couple (classe × période) ? */
    public function existePublie(int|string $classeId, int|string $periodeId): bool;

    /**
     * Écrit un lot de bulletins et leurs lignes, en une transaction.
     *
     * Chaque entrée : ['bulletin' => array<string,mixed>, 'lignes' => array<int,array<string,mixed>>].
     * Les bulletins déjà présents sont mis à jour ; le conseil de classe déjà
     * saisi (décision, distinction, observations) est préservé.
     *
     * @return Collection<int, Bulletin>
     */
    public function enregistrerLot(array $donnees): Collection;

    public function updateConseil(Bulletin $bulletin, array $data): Bulletin;

    /** Publie un lot de bulletins et retourne le nombre de lignes affectées. */
    public function publier(Collection $bulletins, int $userId): int;

    /** Ramène un lot de bulletins à l'état de brouillon. */
    public function depublier(Collection $bulletins): int;
}
