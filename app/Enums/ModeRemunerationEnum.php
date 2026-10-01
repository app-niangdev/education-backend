<?php

namespace App\Enums;

/**
 * Comment se lit le montant porte par `salaire_base` d'un enseignant.
 *
 * Un vacataire est souvent paye a l'heure, un permanent au mois : sans cette
 * distinction, le meme chiffre designait tantot un salaire mensuel tantot un
 * taux horaire, sans qu'on puisse les distinguer.
 */
enum ModeRemunerationEnum: string
{
    case MENSUEL = 'MENSUEL';
    case HORAIRE = 'HORAIRE';

    public function libelle(): string
    {
        return match ($this) {
            self::MENSUEL => 'Mensuelle',
            self::HORAIRE => 'Horaire',
        };
    }

    /** Unite affichee a cote du montant. */
    public function unite(): string
    {
        return match ($this) {
            self::MENSUEL => 'FCFA/mois',
            self::HORAIRE => 'FCFA/h',
        };
    }
}
