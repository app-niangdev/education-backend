<?php

namespace App\Interfaces;

use App\Models\User;

/**
 * Les liens qui permettent de choisir un mot de passe sans en connaitre un :
 * celui de l'oubli, et celui qui ouvre un compte fraichement cree.
 */
interface PasswordResetServiceInterface
{
    /**
     * Emet le lien d'activation d'un compte nouvellement cree.
     *
     * A la difference de l'oubli, l'appelant est ici un administrateur qui
     * vient de creer le compte : il a droit a une reponse franche, et le
     * silence de `envoyerLien` — qui protege l'anonymat des comptes — n'aurait
     * pas de sens.
     *
     * @return string ACTIVATION_LINK_SENT ou ACTIVATION_IMPOSSIBLE.
     */
    public function envoyerLienActivation(User $user): string;

    /**
     * Emet un lien de reinitialisation a la demande de l'administration.
     *
     * Le manager agit sur un compte qu'il administre : la reponse est franche,
     * la ou `envoyerLien` tait tout pour ne rien reveler a un inconnu.
     *
     * @param User $auteur L'administrateur a l'origine de la demande, journalise.
     * @return string REINIT_ADMIN_SENT, REINIT_ADMIN_SANS_EMAIL ou REINIT_ADMIN_INACTIF.
     */
    public function envoyerLienAdministratif(User $user, User $auteur): string;

    /**
     * Emet un lien de reinitialisation si l'adresse correspond a un compte.
     *
     * Ne dit jamais si l'adresse existe : la reponse du controleur est la meme
     * dans tous les cas.
     *
     * @return string RESET_LINK_SENT, RESET_ACCOUNT_UNKNOWN ou RESET_THROTTLED.
     */
    public function envoyerLien(string $email, string $ip): string;

    /**
     * Consomme un jeton et remplace le mot de passe.
     *
     * @return string PASSWORD_RESET, RESET_TOKEN_INVALID ou RESET_TOKEN_EXPIRED.
     */
    public function reinitialiser(string $email, string $token, string $motDePasse, string $ip): string;

    /** Supprime les jetons perimes. */
    public function purger(): int;
}
