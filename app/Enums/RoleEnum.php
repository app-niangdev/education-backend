<?php

namespace App\Enums;

enum RoleEnum: int
{
    case Admin      = 1;
    case Manager    = 2;
    case Supervisor = 3;
    case Treasurer  = 4;
    case Teacher    = 5;

    /**
     * Le tuteur legal de l'eleve. Contrairement aux cinq autres, ce n'est pas
     * un membre du personnel : son compte n'ouvre aucun module de gestion, il
     * ne sert qu'a dialoguer avec l'etablissement et a consulter ce qui
     * concerne ses propres enfants.
     */
    case Tuteur     = 6;
}
