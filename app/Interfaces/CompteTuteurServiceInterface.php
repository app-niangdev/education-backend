<?php

namespace App\Interfaces;

use App\Models\Tuteur;
use App\Models\User;

interface CompteTuteurServiceInterface
{
    /**
     * Ouvre un acces de connexion. Le mot de passe genere n'est retourne
     * qu'ici : il n'est stocke nulle part en clair.
     *
     * @return array{tuteur: Tuteur, mot_de_passe: string}
     */
    public function creer(int|string $tuteurId, array $data, User $authUser): array;

    /** @return array{tuteur: Tuteur, mot_de_passe: string} */
    public function reinitialiserMotDePasse(int|string $tuteurId, User $authUser): array;

    /** Ferme l'acces sans supprimer la fiche tuteur. */
    public function revoquer(int|string $tuteurId, User $authUser): Tuteur;
}
