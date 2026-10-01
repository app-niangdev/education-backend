<?php

namespace App\Services\Concerns;

use Illuminate\Support\Str;

/**
 * Ce qui accompagne la naissance d'un compte du personnel.
 *
 * Un compte ne nait plus avec un mot de passe connu de l'application. Il
 * portait jusqu'ici la meme chaine pour tout l'etablissement — versionnee dans
 * le depot, valable sur chaque compte tant que son titulaire ne s'en etait pas
 * servi. Desormais il nait ferme, et seul le lien envoye a son titulaire
 * l'ouvre.
 *
 * Les quatre services qui creent des comptes (enseignant, surveillant,
 * tresorier, et la creation directe d'utilisateur) partagent ce traitement.
 */
trait OuvreLeCompte
{
    /**
     * Les champs d'authentification d'un compte qui n'a pas encore de
     * proprietaire actif.
     *
     * Le mot de passe est un aleatoire que personne ne connait — ni
     * l'administrateur qui cree le compte, ni le titulaire, ni le code. Il
     * n'existe que pour que la colonne ne soit pas vide : c'est le lien
     * d'activation qui donnera au titulaire le moyen de choisir le sien.
     *
     * @return array{password: string, must_change_password: bool}
     */
    protected function identifiantsDeDepart(): array
    {
        return [
            'password' => Str::random(64),
            // Le mot de passe sera choisi par son titulaire au moment de
            // l'activation : il n'aura rien a changer en arrivant.
            'must_change_password' => false,
        ];
    }
}
