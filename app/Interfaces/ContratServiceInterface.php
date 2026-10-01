<?php

namespace App\Interfaces;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ContratServiceInterface
{
    public function list(int $perPage, string $search, array $filtres = []): LengthAwarePaginator;

    public function find(int|string $id): Contrat;

    /** L'historique contractuel d'un employe (type + id du profil). */
    public function historique(string $type, int|string $id): Collection;

    /**
     * Cree le contrat initial d'un employe qu'on vient d'enregistrer.
     *
     * Appele depuis la creation du profil, dans la meme transaction : un membre
     * du personnel n'existe jamais sans contrat.
     */
    public function creerPourNouveauProfil(Model $contractable, array $donnees, User $authUser): Contrat;

    public function create(array $data, User $authUser): Contrat;

    public function update(int|string $id, array $data, User $authUser): Contrat;

    /** Met fin au contrat avant son terme, avec motif. */
    public function resilier(int|string $id, array $data, User $authUser): Contrat;

    /** Ouvre un nouveau contrat prenant la suite d'un precedent. */
    public function renouveler(int|string $id, array $data, User $authUser): Contrat;

    public function delete(int|string $id, User $authUser): void;

    /**
     * Tout ce que le template du contrat imprime attend : le contrat, l'employe,
     * l'etablissement, le QR code et son URL de verification.
     *
     * @return array<string, mixed>
     */
    public function dataForPdf(int|string $id): array;

    /**
     * Le resume public d'un contrat, retrouve par son code de verification.
     *
     * Sert la page ouverte depuis le QR code d'un document imprime : elle est
     * accessible sans authentification, et ne montre donc que ce qui atteste
     * l'authenticite du papier — jamais la remuneration.
     *
     * @return array<string, mixed>|null Null si le code ne correspond a rien.
     */
    public function verifierParCode(string $code): ?array;
}
