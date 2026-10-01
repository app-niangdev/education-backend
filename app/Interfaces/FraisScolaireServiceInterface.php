<?php

namespace App\Interfaces;

use App\Models\FraisScolaire;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FraisScolaireServiceInterface
{
    public function list(int $perPage, string $search): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int|string $id): FraisScolaire;

    public function create(array $data, User $authUser): FraisScolaire;

    public function update(int|string $id, array $data, User $authUser): FraisScolaire;

    public function delete(int|string $id, User $authUser): void;

    /**
     * Baremes de l'annee scolaire en cours, tries par cycle puis par niveau.
     * Collection vide si aucune annee n'est en cours.
     *
     * @return Collection<int, FraisScolaire>
     */
    public function grilleAnneeEnCours(): Collection;

    /**
     * Referentiels du formulaire : l'annee scolaire en cours (seule sur laquelle
     * un bareme peut se creer) et les niveaux qui n'y ont pas encore de bareme.
     *
     * @return array{annee: ?\App\Models\AnneeScolaire, niveaux: Collection<int, \App\Models\Niveau>}
     */
    public function referentielsFormulaire(int|string|null $fraisId = null): array;

    /**
     * Genere la grille tarifaire d'une annee scolaire en recopiant celle de
     * l'annee precedente. Idempotent : ne remplace ni ne duplique jamais un
     * bareme deja defini (actif ou soft-supprime).
     *
     * @return int Le nombre de baremes reellement crees (0 si tout existait deja).
     */
    public function genererGrille(int|string $anneeScolaireId, User $authUser): int;
}
