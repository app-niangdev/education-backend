<?php

namespace App\Enums;

/**
 * Le cycle d'un niveau. L'ordre des niveaux est relatif au cycle : le passage
 * de classe cherche le successeur d'un niveau à l'intérieur du même cycle.
 */
enum CycleNiveauEnum: string
{
    case PRESCOLAIRE = 'PRESCOLAIRE';
    case PRIMAIRE = 'PRIMAIRE';
    case COLLEGE = 'COLLEGE';
    case LYCEE = 'LYCEE';
    case UNIVERSITE = 'UNIVERSITE';

    public function libelle(): string
    {
        return match ($this) {
            self::PRESCOLAIRE => 'Préscolaire',
            self::PRIMAIRE    => 'Primaire',
            self::COLLEGE     => 'Collège',
            self::LYCEE       => 'Lycée',
            self::UNIVERSITE  => 'Université',
        };
    }
}
