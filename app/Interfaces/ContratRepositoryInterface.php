<?php

namespace App\Interfaces;

use App\Models\Contrat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ContratRepositoryInterface
{
    public function paginate(int $perPage, string $search, array $filtres = []): LengthAwarePaginator;

    public function findById(int|string $id): Contrat;

    /**
     * Le contrat porteur de ce code de verification, ou null.
     *
     * Sert la page publique ouverte depuis un QR code : contrairement a
     * findById(), un code inconnu n'est pas une erreur mais une reponse — le
     * document presente n'est simplement pas authentique.
     */
    public function findByCodeVerification(string $code): ?Contrat;

    /** L'historique contractuel d'un employe, du plus recent au plus ancien. */
    public function forContractable(Model $contractable): Collection;

    public function create(array $data): Contrat;

    public function update(Contrat $contrat, array $data): Contrat;

    public function delete(Contrat $contrat): void;

    /** Le numero suivant pour l'annee donnee (ex. CTR-2026-0007). */
    public function nextNumero(int $annee): string;

    /** Bascule en EXPIRE les contrats actifs dont le terme est depasse. */
    public function expirerEchus(): int;
}
