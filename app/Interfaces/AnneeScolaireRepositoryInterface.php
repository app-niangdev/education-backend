<?php

namespace App\Interfaces;

use App\Models\AnneeScolaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnneeScolaireRepositoryInterface
{
    public function paginate(int $perPage, string $search): LengthAwarePaginator;

    public function all(): \Illuminate\Database\Eloquent\Collection;

    public function findById(int|string $id): AnneeScolaire;

    public function create(array $data): AnneeScolaire;

    public function update(AnneeScolaire $annee, array $data): AnneeScolaire;

    public function existeAnneeActive(): bool;

    public function activer(AnneeScolaire $annee): AnneeScolaire;

    public function desactiver(AnneeScolaire $annee): AnneeScolaire;

    public function desactiverAutresAnnees(?AnneeScolaire $sauf = null): void;

    public function delete(AnneeScolaire $annee): void;

    public function isUsed(AnneeScolaire $annee): bool;

    /** @return string[] Libelles des donnees rattachees, vide si aucune. */
    public function dependances(AnneeScolaire $annee): array;
}
