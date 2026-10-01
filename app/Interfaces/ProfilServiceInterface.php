<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Http\UploadedFile;

interface ProfilServiceInterface
{
    /**
     * Enregistre les coordonnees du compte connecte, et sa photo s'il en
     * depose une.
     *
     * @param  array{phone_one: string, phone_two: ?string, address: ?string}  $donnees
     */
    public function mettreAJour(
        User $user,
        array $donnees,
        ?UploadedFile $photo = null,
        bool $supprimerPhoto = false
    ): User;

    /**
     * La representation du compte attendue par le front : les memes champs que
     * `/auth/me`, pour que l'ecran se rafraichisse sans second appel.
     */
    public function representer(User $user): array;
}
