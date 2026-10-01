<?php

namespace App\Interfaces;

use App\Models\Depense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DepenseRepositoryInterface
{
    /**
     * @param array{
     *   search?:string, categorie?:string, mode_paiement?:string,
     *   annee_scolaire_id?:int|string, date?:string,
     *   date_from?:string, date_to?:string, mois?:int, annee?:int
     * } $filters
     */
    public function paginate(int $perPage, array $filters): LengthAwarePaginator;

    /** @return Collection<int, Depense> */
    public function all(array $filters = []): Collection;

    public function findById(int|string $id): Depense;

    public function create(array $data): Depense;

    public function update(Depense $depense, array $data): Depense;

    public function delete(Depense $depense): void;

    /**
     * Totaux du peuplement filtre : montant global et repartition par
     * categorie. Calcules en base, sur l'ensemble des lignes filtrees et non
     * sur la seule page courante.
     *
     * @return array{total:int, nombre:int, par_categorie:array<int, array{categorie:string, libelle:string, total:int, nombre:int}>}
     */
    public function totaux(array $filters): array;
}
