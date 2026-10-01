<?php

namespace App\Interfaces;

use App\Models\Inscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InscriptionRepositoryInterface
{
    public function paginate(int $perPage, string $search, array $filters = []): LengthAwarePaginator;

    public function all(): Collection;

    public function findById(int|string $id): Inscription;

    public function create(array $data): Inscription;

    public function update(Inscription $inscription, array $data): Inscription;

    public function delete(Inscription $inscription): void;

    /**
     * Numero sequentiel au format ENR-001-26, remis a 001 a chaque annee scolaire.
     */
    public function nextNumeroInscription(int|string $anneeScolaireId): string;

    /**
     * Vrai si l'eleve a deja ete inscrit lors d'une annee scolaire anterieure.
     */
    public function elevePossedeInscriptionAnterieure(int|string $eleveId, int|string $anneeScolaireId): bool;

    /**
     * Inscription active (non annulee) de l'eleve dont l'annee scolaire n'est
     * pas encore cloturee, ou null. Sert a interdire une double inscription
     * tant que l'annee en cours de l'eleve n'est pas terminee.
     */
    public function inscriptionActiveNonCloturee(int|string $eleveId): ?Inscription;

    public function isUsed(Inscription $inscription): bool;
}
