<?php

namespace App\Interfaces;

use App\Models\FraisScolaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FraisScolaireRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): FraisScolaire;

    public function create(array $data): FraisScolaire;

    public function update(FraisScolaire $frais, array $data): FraisScolaire;

    public function delete(FraisScolaire $frais): void;

    /**
     * Vrai si un bareme existe deja pour cette combinaison (annee, niveau).
     */
    public function existsPour(int|string $anneeScolaireId, int|string $niveauId, int|string|null $ignoreId = null): bool;

    /**
     * Niveaux encore sans bareme sur cette annee scolaire : les seuls qu'un
     * formulaire de creation puisse proposer. $niveauCourantId, en modification,
     * conserve le niveau deja porte par le bareme edite.
     *
     * @return Collection<int, \App\Models\Niveau>
     */
    public function niveauxSansBareme(int|string $anneeScolaireId, int|string|null $niveauCourantId = null): Collection;

    /**
     * Baremes d'une annee scolaire, dans l'ordre de progression des niveaux.
     *
     * @return Collection<int, FraisScolaire>
     */
    public function pourAnnee(int|string $anneeScolaireId): Collection;
}
