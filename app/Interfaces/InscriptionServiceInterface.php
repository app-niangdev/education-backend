<?php

namespace App\Interfaces;

use App\Models\Inscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InscriptionServiceInterface
{
    public function list(int $perPage, string $search, array $filters = []): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Inscription;

    public function create(array $data, User $authUser): Inscription;

    public function update(int|string $id, array $data, User $authUser): Inscription;

    public function annuler(int|string $id, ?string $motif, User $authUser): Inscription;

    public function delete(int|string $id, User $authUser): void;

    /**
     * Donnees de la fiche de renseignement (frais + echeancier) pour le PDF.
     */
    public function dataForFichePdf(int|string $id): array;
}
