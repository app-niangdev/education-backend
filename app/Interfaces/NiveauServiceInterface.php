<?php

namespace App\Interfaces;

use App\Models\Niveau;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface NiveauServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    /**
     * Les niveaux avec le barème de l'année en cours, dans l'ordre de
     * progression scolaire. C'est ce qu'affiche l'interface unifiée.
     *
     * @return array{annee: \App\Models\AnneeScolaire|null, niveaux: Collection}
     */
    public function grilleAnneeEnCours(): array;

    public function find(int|string $id): Niveau;

    public function create(array $data, User $authUser): Niveau;

    public function update(int|string $id, array $data, User $authUser): Niveau;

    public function delete(int|string $id, User $authUser): void;
}
