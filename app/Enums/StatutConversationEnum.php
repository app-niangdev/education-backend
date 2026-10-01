<?php

namespace App\Enums;

/**
 * Le cycle de vie d'une conversation, du point de vue du service qui la traite.
 *
 * OUVERTE      : le fil attend une action de l'etablissement.
 * EN_COURS     : un agent l'a prise en charge et a repondu au moins une fois.
 * ESCALADEE    : le service de premier niveau n'a pas pu trancher, le fil est
 *                remonte a la direction. Le tuteur n'en decide jamais.
 * RESOLUE      : le service considere la demande traitee. Le tuteur peut
 *                toujours ecrire, ce qui rouvre le fil.
 * ARCHIVEE     : le fil est clos, plus personne n'y ecrit.
 */
enum StatutConversationEnum: string
{
    case OUVERTE   = 'OUVERTE';
    case EN_COURS  = 'EN_COURS';
    case ESCALADEE = 'ESCALADEE';
    case RESOLUE   = 'RESOLUE';
    case ARCHIVEE  = 'ARCHIVEE';

    public function libelle(): string
    {
        return match ($this) {
            self::OUVERTE   => 'Ouverte',
            self::EN_COURS  => 'En cours de traitement',
            self::ESCALADEE => 'Escaladée à la direction',
            self::RESOLUE   => 'Résolue',
            self::ARCHIVEE  => 'Archivée',
        };
    }

    /**
     * Un fil archive n'accepte plus de message : c'est le seul etat vraiment
     * terminal. RESOLUE reste ouvert a l'ecriture, parce qu'une reponse jugee
     * suffisante par l'ecole ne l'est pas toujours pour le tuteur.
     */
    public function accepteMessage(): bool
    {
        return $this !== self::ARCHIVEE;
    }

    /** Les valeurs, pour les regles de validation et les colonnes enum. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
