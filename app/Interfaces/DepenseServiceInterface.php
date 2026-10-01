<?php

namespace App\Interfaces;

use App\Models\Depense;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DepenseServiceInterface
{
    public function list(int $perPage, array $filters): LengthAwarePaginator;

    /** @return Collection<int, Depense> */
    public function all(array $filters = []): Collection;

    public function find(int|string $id): Depense;

    public function create(array $data, User $authUser): Depense;

    public function update(int|string $id, array $data, User $authUser): Depense;

    /** Le manager engage la depense : elle entre alors dans les totaux. */
    public function valider(int|string $id, User $authUser): Depense;

    /** Le refus conserve la ligne et son motif, il ne l'efface pas. */
    public function refuser(int|string $id, string $motif, User $authUser): Depense;

    public function delete(int|string $id, User $authUser): void;

    /**
     * Totaux du peuplement filtre (montant global + repartition par categorie).
     */
    public function totaux(array $filters): array;

    /**
     * Les mois de l'annee scolaire en cours, du premier au dernier, avec le
     * total depense sur chacun. C'est ce qui alimente le filtre « par mois ».
     *
     * @return array{annee_scolaire:?\App\Models\AnneeScolaire, mois:array<int, array{mois:int, annee:int, libelle:string, total:int, nombre:int}>}
     */
    public function moisAnneeEnCours(): array;
}
