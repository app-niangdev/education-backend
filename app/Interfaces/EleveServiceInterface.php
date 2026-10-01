<?php

namespace App\Interfaces;

use App\Models\Eleve;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EleveServiceInterface
{
    public function list(int $perPage, string $search, array $filters = []): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): Eleve;

    public function create(array $data, User $authUser): Eleve;

    public function update(int|string $id, array $data, User $authUser): Eleve;

    public function delete(int|string $id, User $authUser): void;

    public function restore(int|string $id, User $authUser): Eleve;

    /**
     * Reprise de donnees : cree en masse les eleves deja scolarises.
     *
     * Retourne le compte-rendu ligne par ligne, l'appelant en fait un rapport.
     *
     * @param  array<int, array{ligne?: int, eleve: array, tuteur?: array}>  $lignes
     * @return array{crees: int, ignores: int, echecs: int, details: array<int, array>}
     */
    public function importer(array $lignes, User $authUser): array;

    /**
     * Aligne eleves.classe_actuelle_id sur la classe de l'inscription courante.
     */
    public function synchroniserClasseActuelle(int|string $eleveId, ?int $classeId): void;
}
