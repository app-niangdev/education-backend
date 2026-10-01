<?php

/**
 * Reglages de la protection du formulaire de connexion.
 *
 * Les valeurs par defaut correspondent a la regle metier : cinq essais avant
 * le premier blocage d'une minute, puis un doublement a chaque nouvel echec,
 * jusqu'a vingt-quatre heures.
 */
return [

    // Echecs consecutifs toleres avant que l'IP ne soit bloquee.
    'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),

    // Duree du premier blocage, en secondes. Les suivantes la doublent.
    'base_block_seconds' => (int) env('LOGIN_BASE_BLOCK_SECONDS', 60),

    // Plafond : 24 h. Au-dela, le doublement cesse.
    'max_block_seconds' => (int) env('LOGIN_MAX_BLOCK_SECONDS', 86400),

    'otp' => [
        // Duree de vie d'un code, en secondes.
        'ttl' => (int) env('LOGIN_OTP_TTL', 300),

        // Saisies erronees tolerees pour un meme challenge.
        'max_attempts' => (int) env('LOGIN_OTP_MAX_ATTEMPTS', 5),

        // Renvois de code autorises par challenge.
        'max_resend' => (int) env('LOGIN_OTP_MAX_RESEND', 3),

        // Delai minimal entre deux envois, en secondes : sans lui, le bouton
        // « renvoyer » devient un robinet a e-mails.
        'resend_cooldown' => (int) env('LOGIN_OTP_RESEND_COOLDOWN', 60),
    ],

    // Nettoyage periodique.
    'prune' => [
        // Age, en jours, au-dela duquel une ligne login_security inactive et
        // sans blocage en cours est supprimee.
        'security_days' => (int) env('LOGIN_PRUNE_SECURITY_DAYS', 30),

        // Idem pour les challenges expires ou consommes.
        'challenges_days' => (int) env('LOGIN_PRUNE_CHALLENGES_DAYS', 7),
    ],

    /*
     * Routes exemptees du blocage global.
     *
     * Le blocage vise les requetes d'une IP suspecte, mais ces deux points
     * d'entree servent des tiers sans compte : celui qui scanne le QR code d'un
     * contrat imprime, ou le visiteur de la page d'accueil publique. Les fermer
     * parce qu'un autre poste du meme reseau a rate sa connexion punirait un
     * innocent sans rien proteger — ces routes ne donnent acces a aucune donnee
     * sensible et n'offrent aucune prise a une attaque par force brute.
     *
     * Motifs compares au chemin complet, joker `*` accepte.
     */
    'unblocked_paths' => [
        'api/verification-contrat/*',
        'api/etablissement/infos',
        'api/liste-frais-scolaires',
    ],
];
