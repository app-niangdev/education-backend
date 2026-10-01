<?php

namespace App\Interfaces;

use App\Models\LoginSecurity;

/**
 * La memoire des tentatives de connexion, par adresse IP.
 */
interface LoginSecurityServiceInterface
{
    /**
     * L'etat de securite d'une IP, sans le creer s'il n'existe pas.
     *
     * Le middleware de blocage passe par la a chaque requete : creer une ligne
     * pour chaque visiteur ferait de cette table un journal de trafic.
     */
    public function etat(string $ip): ?LoginSecurity;

    /**
     * Enregistre un echec et pose le blocage si le seuil est atteint.
     *
     * @return LoginSecurity l'etat apres coup — bloque ou non.
     */
    public function enregistrerEchec(string $ip): LoginSecurity;

    /**
     * Solde le passif d'une IP apres une authentification menee a son terme.
     *
     * Compteur, palier, blocage et drapeau `was_blocked` repartent de zero :
     * la sequence suivante recommencera au premier palier.
     */
    public function reinitialiser(string $ip): void;

    /**
     * Vrai si l'IP sort d'un blocage non encore solde par une authentification
     * complete. C'est cette condition, et non l'echec lui-meme, qui rend l'OTP
     * obligatoire malgre un mot de passe correct.
     */
    public function aEteBloquee(string $ip): bool;

    /** Supprime les etats dormants, sans blocage en cours. */
    public function purger(int $joursInactivite): int;
}
