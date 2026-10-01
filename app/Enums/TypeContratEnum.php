<?php

namespace App\Enums;

/**
 * Nature juridique de l'engagement.
 *
 * Reprend les trois valeurs que portaient les colonnes `type_contrat` des
 * tables de profil, en minuscules pour rester compatible avec les donnees
 * deja en base (aucune reecriture des lignes existantes n'est necessaire).
 */
enum TypeContratEnum: string
{
    case PERMANENT = 'permanent';
    case VACATAIRE = 'vacataire';
    case STAGIAIRE = 'stagiaire';

    public function libelle(): string
    {
        return match ($this) {
            self::PERMANENT => 'Permanent',
            self::VACATAIRE => 'Vacataire',
            self::STAGIAIRE => 'Stagiaire',
        };
    }

    /**
     * Un permanent est engage sans terme : sa date de fin reste vide. Les deux
     * autres sont bornes dans le temps, ce qui rend `date_fin` obligatoire.
     */
    public function exigeDateFin(): bool
    {
        return $this !== self::PERMANENT;
    }
}
